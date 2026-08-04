<?php

namespace Tests\Unit\Llm;

use App\Services\Llm\ChatCompletionProxy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatCompletionProxyTest extends TestCase
{
    #[Test]
    public function it_normalizes_api_root_with_and_without_v1_suffix(): void
    {
        $proxy = new ChatCompletionProxy;

        $this->assertSame(
            'http://localhost:1234/v1',
            $proxy->normalizeApiRoot('http://localhost:1234'),
        );

        $this->assertSame(
            'http://localhost:1234/v1',
            $proxy->normalizeApiRoot('http://localhost:1234/v1/'),
        );
    }
}
