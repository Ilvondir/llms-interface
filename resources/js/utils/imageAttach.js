import { MAX_CONTENT_CHARS } from './contentParts.js';

export const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

/**
 * @param {File|Blob} file
 * @param {{
 *   maxEdge?: number,
 *   quality?: number,
 *   maxChars?: number,
 * }} [options]
 * @returns {Promise<string>} data URL
 */
export async function fileToCompressedDataUrl(file, options = {}) {
    const maxEdge = options.maxEdge ?? 2048;
    const quality = options.quality ?? 0.82;
    const maxChars = options.maxChars ?? MAX_CONTENT_CHARS;

    if (! file || typeof file.type !== 'string' || ! ACCEPTED_IMAGE_TYPES.includes(file.type)) {
        throw new Error('Unsupported image type. Use JPEG, PNG, GIF, or WebP.');
    }

    const bitmap = await createImageBitmap(file);
    const scale = Math.min(1, maxEdge / Math.max(bitmap.width, bitmap.height));
    const width = Math.max(1, Math.round(bitmap.width * scale));
    const height = Math.max(1, Math.round(bitmap.height * scale));

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;

    const context = canvas.getContext('2d');

    if (! context) {
        bitmap.close();
        throw new Error('Could not process image.');
    }

    context.drawImage(bitmap, 0, 0, width, height);
    bitmap.close();

    const outputType = file.type === 'image/png' || file.type === 'image/gif'
        ? 'image/png'
        : 'image/jpeg';

    let dataUrl = canvas.toDataURL(outputType, quality);

    if (dataUrl.length > maxChars && outputType === 'image/png') {
        dataUrl = canvas.toDataURL('image/jpeg', quality);
    }

    if (dataUrl.length > maxChars) {
        const tighter = canvas.toDataURL('image/jpeg', Math.min(quality, 0.65));

        if (tighter.length <= maxChars) {
            return tighter;
        }

        throw new Error('Image is still too large after compression. Try a smaller file.');
    }

    return dataUrl;
}

/**
 * Build OpenAI-style user content from composer payload.
 *
 * @param {{ text?: string, imageDataUrl?: string|null }} payload
 * @returns {string|Array<Record<string, unknown>>|null}
 */
export function buildUserMessageContent({ text = '', imageDataUrl = null } = {}) {
    const trimmed = typeof text === 'string' ? text.trim() : '';

    if (imageDataUrl) {
        const parts = [];

        if (trimmed !== '') {
            parts.push({ type: 'text', text: trimmed });
        }

        parts.push({
            type: 'image_url',
            image_url: { url: imageDataUrl },
        });

        return parts;
    }

    return trimmed === '' ? null : trimmed;
}
