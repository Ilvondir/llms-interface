import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { extractStreamErrorMessage, mapVisionStreamError } from '../../resources/js/utils/visionStreamError.js';

describe('mapVisionStreamError', () => {
    it('rewrites image-related upstream failures', () => {
        assert.equal(
            mapVisionStreamError('Upstream chat completions failed with HTTP 400: images are not supported'),
            "This model doesn't accept images. Try a vision-capable model or remove the attachment.",
        );
        assert.equal(
            mapVisionStreamError('model does not support multimodal input'),
            "This model doesn't accept images. Try a vision-capable model or remove the attachment.",
        );
    });

    it('keeps unrelated stream errors intact', () => {
        assert.equal(
            mapVisionStreamError('Upstream chat completions failed with HTTP 502: connection reset'),
            'Upstream chat completions failed with HTTP 502: connection reset',
        );
    });

    it('is idempotent for the friendly vision message', () => {
        const friendly = mapVisionStreamError('vision model required for image_url');
        assert.equal(mapVisionStreamError(friendly), friendly);
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
