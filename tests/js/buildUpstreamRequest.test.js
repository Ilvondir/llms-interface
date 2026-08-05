import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { buildUpstreamRequest, composeModelMessages } from '../../resources/js/utils/buildUpstreamRequest.js';

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
