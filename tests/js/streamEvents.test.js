import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { classifyStreamEvent, formatToolStatusLine, isToolChainMessage, mcpCallFromToolStatus } from '../../resources/js/utils/streamEvents.js';

describe('classifyStreamEvent', () => {
    it('detects tool_status and mcp_warning before treating payload as delta', () => {
        assert.equal(classifyStreamEvent({ event: 'tool_status', tool: 'exa__web_search_exa', status: 'calling' }), 'tool_status');
        assert.equal(classifyStreamEvent({ event: 'mcp_warning', message: 'discovery failed' }), 'mcp_warning');
    });

    it('classifies usage and delta payloads', () => {
        assert.equal(classifyStreamEvent({ usage: { prompt_tokens: 1 } }), 'usage');
        assert.equal(classifyStreamEvent({ choices: [{ delta: { content: 'hi' } }] }), 'delta');
        assert.equal(classifyStreamEvent({ choices: [{ delta: {} }], event: 'tool_status' }), 'tool_status');
    });

    it('returns other for empty or unknown shapes', () => {
        assert.equal(classifyStreamEvent(null), 'other');
        assert.equal(classifyStreamEvent({}), 'other');
        assert.equal(classifyStreamEvent({ event: 'noop' }), 'other');
    });
});

describe('formatToolStatusLine', () => {
    it('formats calling / done / error statuses', () => {
        assert.equal(
            formatToolStatusLine({ tool: 'exa__web_search_exa', status: 'calling' }),
            'Calling exa__web_search_exa…',
        );
        assert.equal(
            formatToolStatusLine({ tool: 'exa__web_search_exa', status: 'done' }),
            'Finished exa__web_search_exa',
        );
        assert.equal(
            formatToolStatusLine({ tool: 'exa__web_search_exa', status: 'error', detail: 'timeout' }),
            'Tool error (exa__web_search_exa): timeout',
        );
    });
});

describe('mcpCallFromToolStatus', () => {
    it('builds a thinking frame with server and arguments', () => {
        const frame = mcpCallFromToolStatus({
            tool_call_id: 'call_1',
            server_id: 'exa',
            server_name: 'Exa',
            tool: 'exa__web_search_exa',
            status: 'calling',
            arguments: { query: 'Wojciech' },
        });

        assert.equal(frame.id, 'call_1');
        assert.equal(frame.serverId, 'exa');
        assert.equal(frame.serverName, 'Exa');
        assert.equal(frame.tool, 'exa__web_search_exa');
        assert.deepEqual(frame.arguments, { query: 'Wojciech' });
        assert.equal(frame.result, null);
    });

    it('keeps result on done events', () => {
        const frame = mcpCallFromToolStatus({
            tool_call_id: 'call_1',
            tool: 'exa__search',
            status: 'done',
            arguments: { q: 'php' },
            result: 'search results',
        });

        assert.equal(frame.status, 'done');
        assert.equal(frame.result, 'search results');
    });
});

describe('isToolChainMessage', () => {
    it('hides tool and assistant tool-call carrier messages', () => {
        assert.equal(isToolChainMessage({ role: 'tool', content: 'x' }), true);
        assert.equal(isToolChainMessage({
            role: 'assistant',
            content: '',
            toolCalls: [{ id: '1' }],
        }), true);
        assert.equal(isToolChainMessage({
            role: 'assistant',
            content: 'Answer',
            toolCalls: [{ id: '1' }],
        }), false);
        assert.equal(isToolChainMessage({ role: 'assistant', content: 'Hi' }), false);
        assert.equal(isToolChainMessage({ role: 'user', content: 'Hi' }), false);
    });
});

describe('classifyStreamEvent history', () => {
    it('detects history_message events', () => {
        assert.equal(
            classifyStreamEvent({ event: 'history_message', message: { role: 'tool', tool_call_id: '1', content: 'x' } }),
            'history_message',
        );
    });
});
