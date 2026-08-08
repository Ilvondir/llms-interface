/**
 * Rough token estimate (≈4 characters / token). Not a real tokenizer —
 * good enough to compare "this message" vs full prompt size.
 *
 * @param {string|Array|null|undefined} text
 * @returns {number}
 */
export function estimateTokenCount(text) {
    let value = '';

    if (typeof text === 'string') {
        value = text.trim();
    } else if (Array.isArray(text)) {
        value = text
            .filter((part) => part?.type === 'text' && typeof part.text === 'string')
            .map((part) => part.text.trim())
            .filter(Boolean)
            .join(' ');
    }

    if (value === '') {
        return 0;
    }

    return Math.max(1, Math.round(value.length / 4));
}

/**
 * Estimate tokens for OpenAI-style tools[] definitions sent with the request.
 *
 * @param {unknown} tools
 * @returns {number}
 */
export function estimateToolsTokens(tools) {
    if (! Array.isArray(tools) || tools.length === 0) {
        return 0;
    }

    try {
        const encoded = JSON.stringify(tools);

        if (typeof encoded !== 'string' || encoded === '') {
            return 0;
        }

        // Framing overhead for the tools block itself.
        return estimateTokenCount(encoded) + 4;
    } catch {
        return 0;
    }
}

/**
 * Estimate tokens for the payload the backend composes (system + history + tools).
 *
 * @param {{
 *   systemPrompt?: string|null,
 *   messages?: Array<{ content?: string }>,
 *   tools?: unknown,
 * }} input
 * @returns {number}
 */
export function estimatePromptTokens({ systemPrompt = null, messages = [], tools = null } = {}) {
    let total = 0;

    if (typeof systemPrompt === 'string' && systemPrompt.trim() !== '') {
        // +4 ≈ message framing overhead
        total += estimateTokenCount(systemPrompt) + 4;
    }

    for (const message of messages) {
        total += estimateTokenCount(message?.content) + 4;
    }

    total += estimateToolsTokens(tools);

    return total;
}
