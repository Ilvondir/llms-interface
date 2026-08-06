/**
 * Classify a parsed SSE JSON payload from /chat/stream.
 *
 * @param {unknown} parsed
 * @returns {'tool_status'|'mcp_warning'|'usage'|'delta'|'other'}
 */
export function classifyStreamEvent(parsed) {
    if (! parsed || typeof parsed !== 'object') {
        return 'other';
    }

    if (parsed.event === 'tool_status') {
        return 'tool_status';
    }

    if (parsed.event === 'mcp_warning') {
        return 'mcp_warning';
    }

    if (parsed.usage) {
        return 'usage';
    }

    if (parsed.choices?.[0]?.delta) {
        return 'delta';
    }

    return 'other';
}

/**
 * @param {{ tool?: string, status?: string, detail?: string }} event
 * @returns {string}
 */
export function formatToolStatusLine(event) {
    const tool = typeof event.tool === 'string' && event.tool !== '' ? event.tool : 'tool';
    const status = typeof event.status === 'string' ? event.status : 'unknown';

    if (status === 'calling') {
        return `Calling ${tool}…`;
    }

    if (status === 'done') {
        return `Finished ${tool}`;
    }

    if (status === 'error') {
        const detail = typeof event.detail === 'string' && event.detail !== ''
            ? `: ${event.detail}`
            : '';

        return `Tool error (${tool})${detail}`;
    }

    return `${tool}: ${status}`;
}
