import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    estimatePromptTokens,
    estimateTokenCount,
    estimateToolsTokens,
} from '../../resources/js/utils/estimateTokens.js';

describe('estimateTokenCount', () => {
    it('returns 0 for empty input', () => {
        assert.equal(estimateTokenCount(''), 0);
        assert.equal(estimateTokenCount('   '), 0);
        assert.equal(estimateTokenCount(null), 0);
    });

    it('estimates from character length', () => {
        assert.equal(estimateTokenCount('abcd'), 1);
        assert.equal(estimateTokenCount('abcdefgh'), 2);
    });
});

describe('estimateToolsTokens', () => {
    it('returns 0 when no tools', () => {
        assert.equal(estimateToolsTokens(null), 0);
        assert.equal(estimateToolsTokens([]), 0);
    });

    it('estimates from serialized tool definitions', () => {
        const tools = [{
            type: 'function',
            function: {
                name: 'exa__web_search_exa',
                description: 'Search the web with Exa',
                parameters: {
                    type: 'object',
                    properties: {
                        query: { type: 'string', description: 'Search query' },
                    },
                    required: ['query'],
                },
            },
        }];

        const encoded = JSON.stringify(tools);
        const expected = Math.max(1, Math.round(encoded.trim().length / 4)) + 4;

        assert.equal(estimateToolsTokens(tools), expected);
        assert.ok(estimateToolsTokens(tools) > 4);
    });
});

describe('estimatePromptTokens', () => {
    it('includes tool definition tokens in the prompt estimate', () => {
        const tools = [{
            type: 'function',
            function: {
                name: 'demo__tool',
                description: 'A demo tool with a reasonably long description for counting',
                parameters: { type: 'object', properties: {} },
            },
        }];

        const withoutTools = estimatePromptTokens({
            systemPrompt: 'Be helpful.',
            messages: [{ role: 'user', content: 'Hello there' }],
        });

        const withTools = estimatePromptTokens({
            systemPrompt: 'Be helpful.',
            messages: [{ role: 'user', content: 'Hello there' }],
            tools,
        });

        assert.equal(withTools, withoutTools + estimateToolsTokens(tools));
        assert.ok(withTools > withoutTools);
    });
});
