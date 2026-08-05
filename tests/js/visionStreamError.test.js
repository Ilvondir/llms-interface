import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    extractStreamErrorMessage,
    mapVisionStreamError,
    messagesIncludeImage,
} from '../../resources/js/utils/visionStreamError.js';

const TINY_PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

describe('mapVisionStreamError', () => {
    it('rewrites image+capability failures only when outbound had an image', () => {
        assert.equal(
            mapVisionStreamError(
                'Upstream chat completions failed with HTTP 400: images are not supported',
                { hadImage: true },
            ),
            "This model doesn't accept images. Try a vision-capable model or remove the attachment.",
        );
        assert.equal(
            mapVisionStreamError('model does not support multimodal input', { hadImage: true }),
            "This model doesn't accept images. Try a vision-capable model or remove the attachment.",
        );
    });

    it('keeps unrelated stream errors intact', () => {
        assert.equal(
            mapVisionStreamError('Upstream chat completions failed with HTTP 502: connection reset', {
                hadImage: true,
            }),
            'Upstream chat completions failed with HTTP 502: connection reset',
        );
        assert.equal(
            mapVisionStreamError('endpoint does not support streaming', { hadImage: true }),
            'endpoint does not support streaming',
        );
    });

    it('does not remap when the request had no image', () => {
        assert.equal(
            mapVisionStreamError('This model does not support images', { hadImage: false }),
            'This model does not support images',
        );
    });

    it('is idempotent for the friendly vision message when hadImage', () => {
        const friendly = mapVisionStreamError('vision model required for image_url unsupported', {
            hadImage: true,
        });
        assert.equal(mapVisionStreamError(friendly, { hadImage: true }), friendly);
    });
});

describe('messagesIncludeImage', () => {
    it('detects image_url parts', () => {
        assert.equal(messagesIncludeImage([{ role: 'user', content: 'hi' }]), false);
        assert.equal(messagesIncludeImage([
            {
                role: 'user',
                content: [{ type: 'image_url', image_url: { url: TINY_PNG } }],
            },
        ]), true);
    });
});

describe('extractStreamErrorMessage', () => {
    it('reads nested error.message and plain strings', () => {
        assert.equal(
            extractStreamErrorMessage({ message: 'Boom' }),
            'Boom',
        );
        assert.equal(
            extractStreamErrorMessage({ error: { message: 'No vision' } }),
            'No vision',
        );
        assert.equal(extractStreamErrorMessage('  plain  '), 'plain');
    });
});
