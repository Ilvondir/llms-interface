<?php

namespace App\Services\Llm;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatCompletionProxy
{
    public function normalizeApiRoot(string $apiBaseUrl): string
    {
        $base = rtrim(trim($apiBaseUrl), '/');

        if (Str::endsWith(Str::lower($base), '/v1')) {
            return $base;
        }

        return $base.'/v1';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function streamChatCompletions(string $apiBaseUrl, array $payload): StreamedResponse
    {
        $apiRoot = $this->normalizeApiRoot($apiBaseUrl);
        $url = $apiRoot.'/chat/completions';

        $upstream = Http::accept('text/event-stream')
            ->asJson()
            ->timeout((int) config('llms.timeout', 300))
            ->connectTimeout((int) config('llms.connect_timeout', 10))
            ->withOptions(['stream' => true])
            ->post($url, [
                ...$payload,
                'stream' => true,
            ]);

        if ($upstream->failed()) {
            $status = $upstream->status();
            $body = $this->safeBodyPreview($upstream);

            throw new RuntimeException(
                "Upstream chat completions failed with HTTP {$status}: {$body}",
                $status >= 400 ? $status : 502,
            );
        }

        $psrBody = $upstream->toPsrResponse()->getBody();

        return response()->stream(function () use ($psrBody, $upstream): void {
            try {
                while (! $psrBody->eof()) {
                    if (connection_aborted()) {
                        break;
                    }

                    echo $psrBody->read(1024);

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }

                    flush();
                }
            } finally {
                $upstream->close();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function listModels(string $apiBaseUrl): array
    {
        $apiRoot = $this->normalizeApiRoot($apiBaseUrl);
        $url = $apiRoot.'/models';

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('llms.models_timeout', 30))
                ->connectTimeout((int) config('llms.connect_timeout', 10))
                ->get($url)
                ->throw();
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Unable to reach model host: '.$exception->getMessage(), 502, $exception);
        } catch (RequestException $exception) {
            $status = $exception->response?->status() ?? 502;
            $body = $exception->response ? $this->safeBodyPreview($exception->response) : $exception->getMessage();

            throw new RuntimeException(
                "Upstream models failed with HTTP {$status}: {$body}",
                $status >= 400 ? $status : 502,
                $exception,
            );
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return $json;
    }

    private function safeBodyPreview(Response $response): string
    {
        $body = $response->body();

        if ($body === '') {
            return '(empty body)';
        }

        return Str::limit($body, 500);
    }
}
