/**
 * Classify a parsed SSE JSON payload from /chat/stream.
 *
 * @param {unknown} parsed
 * @returns {'tool_status'|'mcp_warning'|'history_message'|'mcp_tools'|'usage'|'delta'|'other'}
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

    if (parsed.event === 'history_message') {
        return 'history_message';
    }

    if (parsed.event === 'mcp_tools') {
        return 'mcp_tools';
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

/**
 * Build a Thinking-panel frame for an MCP tool invocation.
 *
 * @param {{
 *   tool_call_id?: string,
 *   server_id?: string,
 *   server_name?: string,
 *   tool?: string,
 *   arguments?: unknown,
 *   result?: unknown,
 *   detail?: string,
 *   status?: string,
 * }} event
 * @returns {{
 *   id: string,
 *   serverId: string,
 *   serverName: string,
 *   tool: string,
 *   arguments: Record<string, unknown>,
 *   result: string|null,
 *   detail: string,
 *   status: string,
 * }}
 */
export function mcpCallFromToolStatus(event) {
    const tool = typeof event?.tool === 'string' && event.tool !== '' ? event.tool : 'tool';
    const serverId = typeof event?.server_id === 'string' ? event.server_id : '';
    const serverName = typeof event?.server_name === 'string' && event.server_name !== ''
        ? event.server_name
        : (serverId || 'MCP');
    const args = event?.arguments;
    const argumentsObject = args && typeof args === 'object' && ! Array.isArray(args)
        ? args
        : {};
    const toolCallId = typeof event?.tool_call_id === 'string' && event.tool_call_id !== ''
        ? event.tool_call_id
        : `${serverId}:${tool}:${JSON.stringify(argumentsObject)}`;
    const result = typeof event?.result === 'string'
        ? event.result
        : (event?.result != null ? String(event.result) : null);
    const detail = typeof event?.detail === 'string' ? event.detail : '';

    return {
        id: toolCallId,
        serverId,
        serverName,
        tool,
        arguments: argumentsObject,
        result,
        detail,
        status: typeof event?.status === 'string' ? event.status : 'calling',
    };
}

/**
 * Pretty-print meaningful tool arguments for the Thinking frame.
 *
 * @param {unknown} value
 * @returns {string}
 */
export function formatMcpArguments(value) {
    try {
        if (typeof value === 'string') {
            return value;
        }

        return JSON.stringify(value ?? {}, null, 2);
    } catch {
        return String(value ?? '{}');
    }
}

/**
 * Tool-chain turns persisted for model history — hide from the visible chat thread.
 *
 * @param {unknown} message
 * @returns {boolean}
 */
export function isToolChainMessage(message) {
    if (! message || typeof message !== 'object') {
        return false;
    }

    if (message.role === 'tool') {
        return true;
    }

    if (message.role !== 'assistant') {
        return false;
    }

    const toolCalls = message.toolCalls ?? message.tool_calls;

    if (! Array.isArray(toolCalls) || toolCalls.length === 0) {
        return false;
    }

    const content = message.content;

    if (typeof content === 'string' && content.trim() !== '') {
        return false;
    }

    return true;
}
