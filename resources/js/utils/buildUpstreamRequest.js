import { isContentEmpty, normalizeMessageContent } from './contentParts.js';

/**
 * Mirror of App\Services\Llm\ChatHistoryComposer for client-side request inspection.
 *
 * @param {string|null|undefined} systemPrompt
 * @param {Array<Record<string, unknown>>} messages
 * @returns {Array<Record<string, unknown>>}
 */
export function composeModelMessages(systemPrompt, messages) {
    const composed = [];
    const trimmedSystemPrompt = typeof systemPrompt === 'string' ? systemPrompt.trim() : '';

    if (trimmedSystemPrompt !== '') {
        composed.push({
            role: 'system',
            content: trimmedSystemPrompt,
        });
    }

    for (const message of messages ?? []) {
        const role = message?.role;

        if (role === 'tool') {
            const toolCallId = message?.tool_call_id ?? message?.toolCallId;

            if (typeof toolCallId !== 'string' || toolCallId === '') {
                continue;
            }

            composed.push({
                role: 'tool',
                tool_call_id: toolCallId,
                content: typeof message?.content === 'string' ? message.content : '',
            });

            continue;
        }

        const toolCalls = message?.tool_calls ?? message?.toolCalls;

        if (role === 'assistant' && Array.isArray(toolCalls) && toolCalls.length > 0) {
            composed.push({
                role: 'assistant',
                content: typeof message?.content === 'string' ? message.content : '',
                tool_calls: toolCalls,
            });

            continue;
        }

        const content = normalizeMessageContent(message?.content);

        if (! ['system', 'user', 'assistant'].includes(role) || content === null || isContentEmpty(content)) {
            continue;
        }

        if (role === 'system' && trimmedSystemPrompt !== '') {
            continue;
        }

        composed.push({ role, content });
    }

    return composed;
}

/**
 * Full JSON body posted to the upstream OpenAI-compatible /chat/completions endpoint
 * (mirrors ChatStreamController + ChatCompletionProxy stream:true).
 *
 * @param {{
 *   model: string,
 *   systemPrompt?: string|null,
 *   messages: Array<{ role: string, content: string|Array<Record<string, unknown>> }>,
 *   temperature?: number|null,
 *   topP?: number|null,
 *   maxTokens?: number|null,
 * }} input
 * @returns {Record<string, unknown>}
 */
export function buildUpstreamRequest({
    model,
    systemPrompt = null,
    messages = [],
    temperature = null,
    topP = null,
    maxTokens = null,
}) {
    const payload = {
        model,
        messages: composeModelMessages(systemPrompt, messages),
        stream_options: {
            include_usage: true,
        },
        stream: true,
    };

    if (temperature != null) {
        payload.temperature = Number(temperature);
    }

    if (topP != null) {
        payload.top_p = Number(topP);
    }

    if (maxTokens != null) {
        payload.max_tokens = Number(maxTokens);
    }

    return payload;
}
