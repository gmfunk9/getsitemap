(function (global) {
    'use strict';

    function ensureString(value) {
        if (typeof value !== 'string') {
            throw new Error('Invalid URL value; expected text input.');
        }

        return value;
    }

    function sanitizeInput(raw) {
        const trimmed = raw.trim();
        if (trimmed === '') {
            throw new Error('Missing field url; add to form.');
        }

        const hasForbiddenCharacter = /[<>'"{}|\\^`\[\]]/.test(trimmed);
        if (hasForbiddenCharacter) {
            throw new Error('URL contains invalid characters; remove brackets or quotes.');
        }

        return trimmed;
    }

    function applyProtocol(urlText) {
        const hasProtocol = /^https?:\/\//i.test(urlText);
        if (hasProtocol) {
            return urlText;
        }

        return 'https://' + urlText;
    }

    function validateProtocol(url) {
        const protocol = url.protocol;
        if (protocol === 'http:') {
            return;
        }

        if (protocol === 'https:') {
            return;
        }

        throw new Error('Invalid protocol; use http or https.');
    }

    function normalizeInput(rawValue) {
        const ensured = ensureString(rawValue);
        const cleaned = sanitizeInput(ensured);
        const prepared = applyProtocol(cleaned);

        let parsedUrl;
        try {
            parsedUrl = new URL(prepared);
        } catch (error) {
            throw new Error('Invalid URL format; parsing failed.');
        }

        validateProtocol(parsedUrl);

        return parsedUrl.toString();
    }

    async function fetchSitemap(normalizedUrl) {
        const endpoint = 'get_sitemap.php?url=' + encodeURIComponent(normalizedUrl);
        const response = await fetch(endpoint);
        if (!response.ok) {
            throw new Error('Request failed with status ' + response.status + '.');
        }

        const payload = await response.json();
        if (payload.success !== true) {
            const messageIsString = typeof payload.message === 'string';
            if (messageIsString) {
                throw new Error(payload.message);
            }

            throw new Error('Unknown error from server.');
        }

        const sitemapIsArray = Array.isArray(payload.sitemap);
        if (!sitemapIsArray) {
            throw new Error('Server response missing sitemap array.');
        }

        return payload.sitemap;
    }

    global.sitemapApi = {
        normalizeInput,
        fetchSitemap
    };
})(window);
