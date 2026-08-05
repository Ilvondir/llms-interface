import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { buildUserMessageContent } from '../../resources/js/utils/imageAttach.js';

const TINY_PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

describe('buildUserMessageContent', () => {
    it('returns trimmed string for text-only messages', () => {
        assert.equal(buildUserMessageContent({ text: '  Hello  ' }), 'Hello');
        assert.equal(buildUserMessageContent({ text: '   ' }), null);
    });

    it('builds OpenAI parts for image with optional text', () => {
        assert.deepEqual(buildUserMessageContent({ text: 'What?', imageDataUrl: TINY_PNG }), [
            { type: 'text', text: 'What?' },
            { type: 'image_url', image_url: { url: TINY_PNG } },
        ]);

        assert.deepEqual(buildUserMessageContent({ text: '', imageDataUrl: TINY_PNG }), [
            { type: 'image_url', image_url: { url: TINY_PNG } },
        ]);
    });
});
