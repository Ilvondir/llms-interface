/**
 * Best-effort mapping of upstream / proxy stream failures to user-facing copy.
 * Keeps generic messages when the failure is unrelated to images.
 *
 * @param {string} raw
 * @returns {string}
 */
export function mapVisionStreamError(raw) {
    const message = typeof raw === 'string' ? raw.trim() : '';

    if (message === '') {
        return 'Stream failed';
    }

    const lower = message.toLowerCase();

    const looksVisionRelated = [
        'image',
        'vision',
        'multimodal',
        'image_url',
        'unsupported media',
        'does not support',
        "don't support",
        'cannot process',
        'not support image',
        'images are not',
        'no vision',
    ].some((needle) => lower.includes(needle));

    if (! looksVisionRelated) {
        return message;
    }

    return "This model doesn't accept images. Try a vision-capable model or remove the attachment.";
}

/**
 * Pull a human-readable error string from a failed fetch response body.
 *
 * @param {unknown} payload
 * @param {string} fallback
 * @returns {string}
 */
export function extractStreamErrorMessage(payload, fallback = 'Stream failed') {
    if (payload == null) {
        return fallback;
    }

    if (typeof payload === 'string') {
        const trimmed = payload.trim();

        return trimmed !== '' ? trimmed : fallback;
    }

    if (typeof payload !== 'object') {
        return fallback;
    }

    if (typeof payload.message === 'string' && payload.message.trim() !== '') {
        return payload.message.trim();
    }

    if (typeof payload.error === 'string' && payload.error.trim() !== '') {
        return payload.error.trim();
    }

    if (payload.error && typeof payload.error === 'object' && typeof payload.error.message === 'string') {
        return payload.error.message.trim();
    }

    try {
        return JSON.stringify(payload);
    } catch {
        return fallback;
    }
}
