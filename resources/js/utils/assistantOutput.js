const THINK_OPEN = '<think>';
const THINK_CLOSE = '</think>';

/**
 * True while an opening <think> has no matching close yet (model still thinking).
 *
 * @param {string} raw
 * @returns {boolean}
 */
export function isThinkBlockOpen(raw) {
    if (typeof raw !== 'string' || raw === '') {
        return false;
    }

    const lower = raw.toLowerCase();
    const openIdx = lower.lastIndexOf(THINK_OPEN);

    if (openIdx === -1) {
        return false;
    }

    const closeIdx = lower.lastIndexOf(THINK_CLOSE);

    return openIdx > closeIdx;
}

/**
 * Split assistant text that embeds reasoning in <think>…</think> tags.
 * Unclosed open tags treat the remainder as reasoning (streaming-friendly).
 *
 * @param {string} raw
 * @returns {{ content: string, reasoning: string }}
 */
export function splitThinkTaggedContent(raw) {
    if (typeof raw !== 'string' || raw === '') {
        return { content: '', reasoning: '' };
    }

    const lower = raw.toLowerCase();
    const contentParts = [];
    const reasoningParts = [];
    let cursor = 0;

    while (cursor < raw.length) {
        const openIdx = lower.indexOf(THINK_OPEN, cursor);

        if (openIdx === -1) {
            contentParts.push(raw.slice(cursor));
            break;
        }

        contentParts.push(raw.slice(cursor, openIdx));

        const afterOpen = openIdx + THINK_OPEN.length;
        const closeIdx = lower.indexOf(THINK_CLOSE, afterOpen);

        if (closeIdx === -1) {
            reasoningParts.push(raw.slice(afterOpen));
            break;
        }

        reasoningParts.push(raw.slice(afterOpen, closeIdx));
        cursor = closeIdx + THINK_CLOSE.length;
    }

    return {
        content: contentParts.join('').replace(/^\n+/, ''),
        reasoning: reasoningParts
            .map((part) => part.trim())
            .filter(Boolean)
            .join('\n\n'),
    };
}

/**
 * @param {string} apiReasoning
 * @param {string} taggedReasoning
 * @returns {string}
 */
export function mergeReasoning(apiReasoning, taggedReasoning) {
    return [apiReasoning, taggedReasoning]
        .map((part) => (typeof part === 'string' ? part.trim() : ''))
        .filter(Boolean)
        .join('\n\n');
}

/**
 * Whether the model is still in the reasoning phase (open think tag or API reasoning before answer).
 *
 * @param {{ rawContent: string, apiReasoning: string, content: string }} input
 * @returns {boolean}
 */
export function isReasoningPhaseActive({ rawContent, apiReasoning, content }) {
    if (isThinkBlockOpen(rawContent)) {
        return true;
    }

    const hasApiReasoning = typeof apiReasoning === 'string' && apiReasoning.trim() !== '';
    const hasAnswer = typeof content === 'string' && content.trim() !== '';

    return hasApiReasoning && ! hasAnswer;
}
