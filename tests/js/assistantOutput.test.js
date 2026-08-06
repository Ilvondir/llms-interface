import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    isThinkBlockOpen,
    splitThinkTaggedContent,
    stripThinkTags,
} from '../../resources/js/utils/assistantOutput.js';

describe('splitThinkTaggedContent', () => {
    it('splits paired think tags into reasoning and content', () => {
        const result = splitThinkTaggedContent('<think>plan</think>\n\nAnswer');

        assert.equal(result.reasoning, 'plan');
        assert.equal(result.content, 'Answer');
    });

    it('keeps unclosed think body as reasoning while streaming', () => {
        const result = splitThinkTaggedContent('<think>still thinking');

        assert.equal(result.reasoning, 'still thinking');
        assert.equal(result.content, '');
        assert.equal(isThinkBlockOpen('<think>still thinking'), true);
    });

    it('strips orphan close tags after tool rounds (no matching open in content)', () => {
        const result = splitThinkTaggedContent('</think>\n\n### Answer\n\n</think>\n\nMore');

        assert.equal(result.reasoning, '');
        assert.equal(result.content, '### Answer\n\nMore');
        assert.equal(result.content.includes('</think>'), false);
    });
});

describe('stripThinkTags', () => {
    it('removes open and close tags from leaked answer text', () => {
        assert.equal(stripThinkTags('</think>\n\nHi'), 'Hi');
        assert.equal(stripThinkTags('A</think>B'), 'AB');
    });
});
