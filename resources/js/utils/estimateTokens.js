/**
 * Rough token estimate (≈4 characters / token). Not a real tokenizer —
 * good enough to compare "this message" vs full prompt size.
 *
 * @param {string|null|undefined} text
 * @returns {number}
 */
export function estimateTokenCount(text) {
    const value = String(text ?? '').trim();

    if (value === '') {
        return 0;
    }

    return Math.max(1, Math.round(value.length / 4));
}

/**
 * Estimate tokens for the payload the backend composes (system + history).
 *
 * @param {{ systemPrompt?: string|null, messages?: Array<{ content?: string }> }} input
 * @returns {number}
 */
export function estimatePromptTokens({ systemPrompt = null, messages = [] } = {}) {
    let total = 0;

    if (typeof systemPrompt === 'string' && systemPrompt.trim() !== '') {
        // +4 ≈ message framing overhead
        total += estimateTokenCount(systemPrompt) + 4;
    }

    for (const message of messages) {
        total += estimateTokenCount(message?.content) + 4;
    }

    return total;
}
