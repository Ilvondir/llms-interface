import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { buildUpstreamRequest, composeModelMessages } from '../../resources/js/utils/buildUpstreamRequest.js';
import {
    contentPlainText,
    isContentEmpty,
    normalizeMessageContent,
    sanitizeRequestPayloadForStorage,
} from '../../resources/js/utils/contentParts.js';

const TINY_PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

describe('composeModelMessages', () => {
    it('prepends system prompt and skips empty assistant content', () => {
        const messages = composeModelMessages('Be helpful.', [
            { role: 'user', content: 'Hello' },
            { role: 'assistant', content: 'Hi' },
            { role: 'assistant', content: '   ' },
        ]);

        assert.deepEqual(messages, [
            { role: 'system', content: 'Be helpful.' },
            { role: 'user', content: 'Hello' },
            { role: 'assistant', content: 'Hi' },
        ]);
    });

    it('skips duplicate system messages when system prompt is set', () => {
        const messages = composeModelMessages('Canonical', [
            { role: 'system', content: 'From history' },
            { role: 'user', content: 'Hi' },
        ]);

        assert.deepEqual(messages, [
            { role: 'system', content: 'Canonical' },
            { role: 'user', content: 'Hi' },
        ]);
    });

    it('preserves OpenAI image_url content parts', () => {
        const parts = [
            { type: 'text', text: 'What is this?' },
            { type: 'image_url', image_url: { url: TINY_PNG } },
        ];

        const messages = composeModelMessages(null, [
            { role: 'user', content: parts, reasoning: 'nope' },
        ]);

        assert.deepEqual(messages, [
            { role: 'user', content: parts },
        ]);
    });

    it('keeps image-only messages and drops empty text parts arrays', () => {
        const messages = composeModelMessages(null, [
            {
                role: 'user',
                content: [{ type: 'image_url', image_url: { url: TINY_PNG } }],
            },
            {
                role: 'user',
                content: [{ type: 'text', text: '   ' }],
            },
        ]);

        assert.deepEqual(messages, [
            {
                role: 'user',
                content: [{ type: 'image_url', image_url: { url: TINY_PNG } }],
            },
        ]);
    });

    it('passes assistant tool_calls and tool result messages through', () => {
        const messages = composeModelMessages(null, [
            { role: 'user', content: 'Hi' },
            {
                role: 'assistant',
                content: '',
                toolCalls: [{
                    id: 'call_1',
                    type: 'function',
                    function: { name: 'exa__search', arguments: '{}' },
                }],
            },
            { role: 'tool', toolCallId: 'call_1', content: 'result' },
        ]);

        assert.equal(messages.length, 3);
        assert.equal(messages[1].role, 'assistant');
        assert.ok(Array.isArray(messages[1].tool_calls));
        assert.equal(messages[2].role, 'tool');
        assert.equal(messages[2].tool_call_id, 'call_1');
    });
});

describe('contentParts helpers', () => {
    it('normalizes and reads plain text from parts', () => {
        const parts = [
            { type: 'text', text: '  Hello  ' },
            { type: 'image_url', image_url: { url: TINY_PNG } },
        ];

        assert.deepEqual(normalizeMessageContent(parts), [
            { type: 'text', text: 'Hello' },
            { type: 'image_url', image_url: { url: TINY_PNG } },
        ]);
        assert.equal(contentPlainText(parts), 'Hello');
        assert.equal(isContentEmpty([{ type: 'text', text: '   ' }]), true);
        assert.equal(isContentEmpty([{ type: 'image_url', image_url: { url: TINY_PNG } }]), false);
    });

    it('sanitizes image_url parts out of request payloads', () => {
        const payload = {
            model: 'demo',
            messages: [
                {
                    role: 'user',
                    content: [
                        { type: 'text', text: 'Hi' },
                        { type: 'image_url', image_url: { url: TINY_PNG } },
                    ],
                },
            ],
            stream: true,
        };

        const sanitized = sanitizeRequestPayloadForStorage(payload);

        assert.deepEqual(sanitized.messages[0].content, [
            { type: 'text', text: 'Hi' },
            { type: 'text', text: '[image omitted]' },
        ]);
        assert.equal(payload.messages[0].content[1].type, 'image_url');
        assert.ok(JSON.stringify(sanitized).length < JSON.stringify(payload).length);
    });
});

describe('buildUpstreamRequest', () => {
    it('builds the full upstream chat completions body', () => {
        const payload = buildUpstreamRequest({
            model: 'qwen/qwen3.5-9b',
            systemPrompt: 'You are helpful.',
            messages: [{ role: 'user', content: 'Hi' }],
            temperature: 0.7,
            topP: 1,
            maxTokens: 512,
        });

        assert.deepEqual(payload, {
            model: 'qwen/qwen3.5-9b',
            messages: [
                { role: 'system', content: 'You are helpful.' },
                { role: 'user', content: 'Hi' },
            ],
            stream_options: { include_usage: true },
            stream: true,
            temperature: 0.7,
            top_p: 1,
            max_tokens: 512,
        });
    });

    it('omits max_tokens when unlimited (null)', () => {
        const payload = buildUpstreamRequest({
            model: 'demo',
            messages: [{ role: 'user', content: 'Hi' }],
            temperature: 0.5,
            topP: 0.9,
            maxTokens: null,
        });

        assert.equal('max_tokens' in payload, false);
        assert.equal(payload.stream, true);
    });
});
