/**
 * Best-effort mapping of upstream / proxy stream failures to user-facing copy.
 * Only remaps when the outbound request included an image and the error looks vision-related.
 *
 * @param {string} raw
 * @param {{ hadImage?: boolean }} [options]
 * @returns {string}
 */
export function mapVisionStreamError(raw, { hadImage = false } = {}) {
    const message = typeof raw === 'string' ? raw.trim() : '';

    if (message === '') {
        return 'Stream failed';
    }

    if (! hadImage) {
        return message;
    }

    const lower = message.toLowerCase();

    const mentionsImage = [
        'image',
        'vision',
        'multimodal',
        'image_url',
        'unsupported media',
    ].some((needle) => lower.includes(needle));

    const mentionsCapability = [
        'not support',
        'does not support',
        "doesn't support",
        "don't support",
        'unsupported',
        'cannot process',
        'can\'t process',
        'not accept',
        'unable to',
    ].some((needle) => lower.includes(needle));

    if (! (mentionsImage && mentionsCapability)) {
        return message;
    }

    return "This model doesn't accept images. Try a vision-capable model or remove the attachment.";
}

/**
 * @param {unknown} messages
 * @returns {boolean}
 */
export function messagesIncludeImage(messages) {
    if (! Array.isArray(messages)) {
        return false;
    }

    return messages.some((message) => {
        const content = message?.content;

        if (! Array.isArray(content)) {
            return false;
        }

        return content.some((part) => part?.type === 'image_url' && typeof part.image_url?.url === 'string');
    });
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
