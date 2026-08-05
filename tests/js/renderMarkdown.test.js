import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { renderMarkdown } from '../../resources/js/utils/renderMarkdown.js';

describe('renderMarkdown', () => {
    it('returns empty string for blank input', () => {
        assert.equal(renderMarkdown(''), '');
        assert.equal(renderMarkdown(null), '');
    });

    it('renders bold and links', () => {
        const html = renderMarkdown('See **docs** at [site](https://example.com).');

        assert.match(html, /<strong>docs<\/strong>/);
        assert.match(html, /<a[^>]+href="https:\/\/example\.com"[^>]*>site<\/a>/);
        assert.match(html, /target="_blank"/);
        assert.match(html, /rel="noopener noreferrer"/);
    });

    it('strips script tags from untrusted markdown', () => {
        const html = renderMarkdown('Hello <script>alert(1)</script> world');

        assert.doesNotMatch(html, /<script/i);
        assert.match(html, /Hello/);
        assert.match(html, /world/);
    });
});
