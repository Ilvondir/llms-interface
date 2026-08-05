import DOMPurify from 'isomorphic-dompurify';
import { marked } from 'marked';

marked.setOptions({
    breaks: true,
    gfm: true,
});

let hooksRegistered = false;

function ensureHooks() {
    if (hooksRegistered) {
        return;
    }

    DOMPurify.addHook('afterSanitizeAttributes', (node) => {
        if (node.tagName === 'A') {
            node.setAttribute('target', '_blank');
            node.setAttribute('rel', 'noopener noreferrer');
        }
    });

    hooksRegistered = true;
}

/**
 * Convert markdown to sanitized HTML for chat message bodies.
 *
 * @param {string} markdown
 * @returns {string}
 */
export function renderMarkdown(markdown) {
    if (typeof markdown !== 'string' || markdown === '') {
        return '';
    }

    ensureHooks();

    const dirty = marked.parse(markdown, { async: false });

    return DOMPurify.sanitize(dirty, {
        USE_PROFILES: { html: true },
    });
}
