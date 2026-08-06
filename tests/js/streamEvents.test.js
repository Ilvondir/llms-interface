import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { classifyStreamEvent, formatToolStatusLine } from '../../resources/js/utils/streamEvents.js';

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
