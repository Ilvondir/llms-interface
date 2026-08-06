import { ref } from 'vue';
import { csrfToken } from '@/composables/chatApi';
import { mergeReasoning, isReasoningPhaseActive, splitThinkTaggedContent } from '@/utils/assistantOutput';
import { extractStreamErrorMessage, mapVisionStreamError, messagesIncludeImage } from '@/utils/visionStreamError';
import { classifyStreamEvent } from '@/utils/streamEvents';

const readDeltaText = (delta) => {
    if (! delta || typeof delta !== 'object') {
        return { content: '', reasoning: '' };
    }

    const content = typeof delta.content === 'string' ? delta.content : '';
    const reasoning = typeof delta.reasoning_content === 'string'
        ? delta.reasoning_content
        : (typeof delta.reasoning === 'string' ? delta.reasoning : '');

    return { content, reasoning };
};

const publishAssistantState = ({
    rawContent,
    apiReasoning,
    onToken,
    onReasoning,
    onThinking,
}) => {
    const split = splitThinkTaggedContent(rawContent);
    const reasoning = mergeReasoning(apiReasoning, split.reasoning);
    const thinking = isReasoningPhaseActive({
        rawContent,
        apiReasoning,
        content: split.content,
    });

    onToken?.(split.content, split.content);
    onReasoning?.(reasoning, reasoning);
    onThinking?.(thinking);

    return { content: split.content, reasoning, thinking };
};

const mapUsageStats = (usage, { ttftMs, elapsedMs, outputChars }) => {
    if (! usage && ttftMs == null && ! outputChars && ! (elapsedMs > 0)) {
        return null;
    }

    const details = usage?.completion_tokens_details ?? usage?.output_tokens_details ?? null;

    const inputTokens = usage?.prompt_tokens
        ?? usage?.input_tokens
        ?? null;

    const outputTokens = usage?.completion_tokens
        ?? usage?.output_tokens
        ?? usage?.total_output_tokens
        ?? null;

    const reasoningTokens = details?.reasoning_tokens
        ?? usage?.reasoning_tokens
        ?? usage?.reasoning_output_tokens
        ?? null;

    const answerTokens = details?.accepted_prediction_tokens
        ?? details?.text_tokens
        ?? (
            outputTokens != null && reasoningTokens != null
                ? Math.max(0, outputTokens - reasoningTokens)
                : null
        );

    const totalTokens = usage?.total_tokens
        ?? (
            inputTokens != null && outputTokens != null
                ? inputTokens + outputTokens
                : null
        );

    const elapsedSeconds = elapsedMs > 0 ? elapsedMs / 1000 : 0;
    const upstreamTokensPerSecond = usage?.tokens_per_second
        ?? usage?.tokens_per_sec
        ?? null;

    const tokensPerSecond = upstreamTokensPerSecond
        ?? (
            outputTokens != null && elapsedSeconds > 0
                ? outputTokens / elapsedSeconds
                : (outputChars > 0 && elapsedSeconds > 0 ? outputChars / elapsedSeconds : null)
        );

    return {
        inputTokens,
        outputTokens,
        reasoningTokens,
        answerTokens,
        totalTokens,
        ttftMs,
        elapsedMs: elapsedMs > 0 ? Math.round(elapsedMs) : null,
        tokensPerSecond,
        usageSource: usage ? 'upstream' : 'client',
    };
};

export function useChatStream() {
    const isStreaming = ref(false);
    const streamError = ref(null);
    let abortController = null;

    const cancel = () => {
        abortController?.abort();
        abortController = null;
        isStreaming.value = false;
    };

    const streamChat = async ({
        apiBaseUrl,
        model,
        systemPrompt,
        messages,
        temperature,
        maxTokens,
        topP,
        enabledMcpServerIds = [],
        mcpServers = [],
        mcpCredentials = [],
        onToken,
        onReasoning,
        onThinking,
        onToolStatus,
        onMcpWarning,
        onHistoryMessage,
        onMcpTools,
        onFinish,
        onError,
    }) => {
        cancel();

        streamError.value = null;
        isStreaming.value = true;
        abortController = new AbortController();

        const startedAt = performance.now();
        let firstTokenAt = null;
        let usage = null;
        let outputChars = 0;
        let rawContent = '';
        let apiReasoning = '';
        let content = '';
        let reasoning = '';
        const hadImage = messagesIncludeImage(messages);

        const publish = () => publishAssistantState({
            rawContent,
            apiReasoning,
            onToken,
            onReasoning,
            onThinking,
        });

        const payload = {
            api_base_url: apiBaseUrl,
            model,
            system_prompt: systemPrompt || null,
            messages,
            temperature,
            top_p: topP,
        };

        if (maxTokens != null) {
            payload.max_tokens = maxTokens;
        }

        if (Array.isArray(enabledMcpServerIds) && enabledMcpServerIds.length > 0) {
            payload.enabled_mcp_server_ids = enabledMcpServerIds;
        }

        if (Array.isArray(mcpServers) && mcpServers.length > 0) {
            payload.mcp_servers = mcpServers;
        }

        if (Array.isArray(mcpCredentials) && mcpCredentials.length > 0) {
            payload.mcp_credentials = mcpCredentials;
        }

        try {
            const response = await fetch(route('chat.stream'), {
                method: 'POST',
                credentials: 'same-origin',
                signal: abortController.signal,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'text/event-stream',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });

            if (! response.ok) {
                let message = `Stream failed (${response.status})`;

                try {
                    const contentType = response.headers.get('Content-Type') ?? '';
                    const rawBody = await response.text();

                    if (contentType.includes('application/json')) {
                        try {
                            message = extractStreamErrorMessage(JSON.parse(rawBody), message);
                        } catch {
                            message = extractStreamErrorMessage(rawBody, message);
                        }
                    } else {
                        message = extractStreamErrorMessage(rawBody, message);
                    }
                } catch {
                    // keep status fallback
                }

                throw new Error(mapVisionStreamError(message, { hadImage }));
            }

            if (! response.body) {
                throw new Error('Streaming is not supported in this browser.');
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            while (true) {
                const { done, value } = await reader.read();

                if (done) {
                    break;
                }

                buffer += decoder.decode(value, { stream: true });
                const chunks = buffer.split('\n');
                buffer = chunks.pop() ?? '';

                for (const rawLine of chunks) {
                    const line = rawLine.trim();

                    if (! line.startsWith('data:')) {
                        continue;
                    }

                    const data = line.slice(5).trim();

                    if (data === '' || data === '[DONE]') {
                        continue;
                    }

                    let parsed;

                    try {
                        parsed = JSON.parse(data);
                    } catch {
                        continue;
                    }

                    const kind = classifyStreamEvent(parsed);

                    if (kind === 'tool_status') {
                        onToolStatus?.(parsed);

                        continue;
                    }

                    if (kind === 'mcp_warning') {
                        onMcpWarning?.(parsed);

                        continue;
                    }

                    if (kind === 'history_message') {
                        onHistoryMessage?.(parsed.message ?? null);

                        continue;
                    }

                    if (kind === 'mcp_tools') {
                        onMcpTools?.(parsed.tools ?? []);

                        continue;
                    }

                    if (parsed.usage) {
                        usage = parsed.usage;
                    }

                    const delta = parsed.choices?.[0]?.delta ?? {};
                    const { content: contentDelta, reasoning: reasoningDelta } = readDeltaText(delta);

                    if (contentDelta || reasoningDelta) {
                        if (firstTokenAt == null) {
                            firstTokenAt = performance.now();
                        }
                    }

                    if (contentDelta) {
                        rawContent += contentDelta;
                        outputChars += contentDelta.length;
                    }

                    if (reasoningDelta) {
                        apiReasoning += reasoningDelta;
                    }

                    if (contentDelta || reasoningDelta) {
                        ({ content, reasoning } = publish());
                    }
                }
            }

            ({ content, reasoning } = publish());
            onThinking?.(false);

            const finishedAt = performance.now();
            const stats = mapUsageStats(usage, {
                ttftMs: firstTokenAt != null ? Math.round(firstTokenAt - startedAt) : null,
                elapsedMs: finishedAt - startedAt,
                outputChars,
            });

            await onFinish?.({ content, reasoning, stats });
        } catch (error) {
            if (error?.name === 'AbortError') {
                ({ content, reasoning } = publish());
                onThinking?.(false);
                await onFinish?.({ content, reasoning, stats: null, aborted: true });
            } else {
                const rawMessage = error instanceof Error ? error.message : 'Stream failed';
                const message = mapVisionStreamError(rawMessage, { hadImage });
                streamError.value = message;
                ({ content, reasoning } = publish());
                onThinking?.(false);
                await onError?.({ message, content, reasoning });
            }
        } finally {
            isStreaming.value = false;
            abortController = null;
        }
    };

    return {
        isStreaming,
        streamError,
        streamChat,
        cancel,
    };
}
