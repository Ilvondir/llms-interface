/**
 * OpenAI-compatible message content helpers (string | content parts).
 * Mirrors App\Support\Chat\MessageContent.
 */

export const MAX_CONTENT_CHARS = 5_500_000;
export const MAX_IMAGES_PER_MESSAGE = 1;
export const IMAGE_DATA_URL_PATTERN = /^data:image\/(jpeg|png|gif|webp);base64,/i;

/**
 * @param {unknown} content
 * @returns {string|Array<Record<string, unknown>>|null}
 */
export function normalizeMessageContent(content) {
    if (typeof content === 'string') {
        const trimmed = content.trim();

        return trimmed === '' ? null : trimmed;
    }

    if (! Array.isArray(content) || content.length === 0) {
        return null;
    }

    const parts = [];
    let imageCount = 0;

    for (const part of content) {
        if (! part || typeof part !== 'object') {
            continue;
        }

        if (part.type === 'text') {
            const text = typeof part.text === 'string' ? part.text.trim() : '';
            parts.push({ type: 'text', text });
            continue;
        }

        if (part.type === 'image_url') {
            const url = part.image_url?.url;
            if (typeof url !== 'string' || url === '') {
                continue;
            }

            imageCount += 1;
            if (imageCount > MAX_IMAGES_PER_MESSAGE) {
                continue;
            }

            parts.push({
                type: 'image_url',
                image_url: { url },
            });
        }
    }

    if (isContentEmpty(parts)) {
        return null;
    }

    return parts;
}

/**
 * @param {unknown} content
 * @returns {boolean}
 */
export function isContentEmpty(content) {
    if (typeof content === 'string') {
        return content.trim() === '';
    }

    if (! Array.isArray(content) || content.length === 0) {
        return true;
    }

    for (const part of content) {
        if (! part || typeof part !== 'object') {
            continue;
        }

        if (part.type === 'text' && typeof part.text === 'string' && part.text.trim() !== '') {
            return false;
        }

        if (part.type === 'image_url') {
            const url = part.image_url?.url;
            if (typeof url === 'string' && url !== '') {
                return false;
            }
        }
    }

    return true;
}

/**
 * @param {unknown} content
 * @returns {string}
 */
export function contentPlainText(content) {
    if (typeof content === 'string') {
        return content.trim();
    }

    if (! Array.isArray(content)) {
        return '';
    }

    for (const part of content) {
        if (part?.type === 'text' && typeof part.text === 'string') {
            const text = part.text.trim();
            if (text !== '') {
                return text;
            }
        }
    }

    return '';
}

/**
 * @param {unknown} content
 * @returns {number}
 */
export function contentCharacterLength(content) {
    if (typeof content === 'string') {
        return content.length;
    }

    if (! Array.isArray(content)) {
        return 0;
    }

    let length = 0;

    for (const part of content) {
        if (part?.type === 'text' && typeof part.text === 'string') {
            length += part.text.length;
        }

        if (part?.type === 'image_url' && typeof part.image_url?.url === 'string') {
            length += part.image_url.url.length;
        }
    }

    return length;
}

/**
 * Strip image bytes from an upstream-shaped request payload before DB persistence.
 *
 * @param {unknown} payload
 * @returns {unknown}
 */
export function sanitizeRequestPayloadForStorage(payload) {
    if (! payload || typeof payload !== 'object' || Array.isArray(payload)) {
        return payload;
    }

    // JSON round-trip: structuredClone fails on Vue reactive proxies held on pending messages.
    let clone;

    try {
        clone = JSON.parse(JSON.stringify(payload));
    } catch {
        return null;
    }

    if (! Array.isArray(clone.messages)) {
        return clone;
    }

    clone.messages = clone.messages.map((message) => {
        if (! message || typeof message !== 'object') {
            return message;
        }

        const content = message.content;

        if (typeof content === 'string') {
            return message;
        }

        if (! Array.isArray(content)) {
            return message;
        }

        const nextContent = [];

        for (const part of content) {
            if (part?.type === 'image_url') {
                nextContent.push({ type: 'text', text: '[image omitted]' });
                continue;
            }

            nextContent.push(part);
        }

        return {
            ...message,
            content: nextContent.length === 1 && nextContent[0]?.type === 'text'
                ? nextContent[0].text
                : nextContent,
        };
    });

    return clone;
}
