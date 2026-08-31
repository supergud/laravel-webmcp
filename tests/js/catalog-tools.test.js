import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { payloadOf, stubBrowser, stubFetch } from './helpers.js';

let tools;

async function loadTools() {
    const module = await import('../../resources/js/webmcp/tools/catalog.js?t=' + Math.random());

    return Object.fromEntries(module.catalogTools.map((tool) => [tool.name, tool]));
}

beforeEach(async () => {
    stubBrowser();
    tools = await loadTools();
});

afterEach(() => {
    vi.unstubAllGlobals();
    vi.resetModules();
});

describe('search_products', () => {
    it('calls the catalogue endpoint and returns the payload both ways', async () => {
        const body = { locale: 'en', total: 1, products: [{ sku: 'LAP-1001' }] };
        stubFetch([{ body }]);

        const result = await tools.search_products.execute({ term: 'AeroBook' });

        expect(payloadOf(result)).toEqual(body);
        expect(result.structuredContent).toEqual(body);
        expect(result.content[0].type).toBe('text');
        expect(result.isError).toBeUndefined();
    });

    it('passes filters through as query parameters', async () => {
        const { calls } = stubFetch([{ body: {} }]);

        await tools.search_products.execute({
            term: 'laptop',
            category: 'laptops',
            min_price: 10000,
            max_price: 50000,
            sort: 'price_asc',
            page: 2,
            per_page: 24,
        });

        const url = new URL(calls[0].url);

        expect(url.pathname).toBe('/api/mcp/products');
        expect(Object.fromEntries(url.searchParams)).toEqual({
            term: 'laptop',
            category: 'laptops',
            min_price: '10000',
            max_price: '50000',
            sort: 'price_asc',
            page: '2',
            per_page: '24',
        });
    });

    it('omits empty filters rather than sending blanks', async () => {
        const { calls } = stubFetch([{ body: {} }]);

        await tools.search_products.execute({ term: '', category: null, min_price: undefined });

        expect(new URL(calls[0].url).search).toBe('');
    });

    it('works with no arguments at all', async () => {
        const { calls } = stubFetch([{ body: {} }]);

        await tools.search_products.execute();

        expect(new URL(calls[0].url).pathname).toBe('/api/mcp/products');
    });

    it('sends the session cookie and never a bearer token', async () => {
        const { calls } = stubFetch([{ body: {} }]);

        await tools.search_products.execute({});

        expect(calls[0].init.credentials).toBe('same-origin');
        expect(calls[0].init.headers.Authorization).toBeUndefined();
    });

    it('does not send a CSRF header on a read', async () => {
        const { calls } = stubFetch([{ body: {} }]);

        await tools.search_products.execute({});

        expect(calls[0].init.headers['X-CSRF-TOKEN']).toBeUndefined();
    });
});

describe('get_product', () => {
    it('fetches by identifier', async () => {
        const { calls } = stubFetch([{ body: { product: { sku: 'LAP-1001' } } }]);

        await tools.get_product.execute({ identifier: 'LAP-1001' });

        expect(new URL(calls[0].url).pathname).toBe('/api/mcp/products/LAP-1001');
    });

    it('escapes an identifier that would otherwise change the path', async () => {
        const { calls } = stubFetch([{ body: {} }]);

        await tools.get_product.execute({ identifier: '../../orders/secret' });

        expect(new URL(calls[0].url).pathname).toBe('/api/mcp/products/..%2F..%2Forders%2Fsecret');
    });

    it('reports a missing product as a tool error rather than throwing', async () => {
        stubFetch([{ status: 404, body: { error: { code: 'not_found', message: 'That product is not available.' } } }]);

        const result = await tools.get_product.execute({ identifier: 'NOPE' });

        expect(result.isError).toBe(true);
        expect(payloadOf(result).error).toEqual({
            code: 'not_found',
            message: 'That product is not available.',
        });
    });
});

describe('list_categories', () => {
    it('takes no arguments and hits the categories endpoint', async () => {
        const { calls } = stubFetch([{ body: { categories: [] } }]);

        await tools.list_categories.execute();

        expect(new URL(calls[0].url).pathname).toBe('/api/mcp/categories');
        expect(tools.list_categories.inputSchema.required).toEqual([]);
    });
});

describe('failure handling', () => {
    it('never throws out of a tool handler', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => {
            throw new TypeError('Failed to fetch');
        }));

        const result = await tools.search_products.execute({});

        expect(result.isError).toBe(true);
        expect(payloadOf(result).error.code).toBe('network_error');
    });

    it('explains rate limiting so an agent can back off', async () => {
        stubFetch([{ status: 429, body: {} }]);

        const result = await tools.search_products.execute({});

        expect(payloadOf(result).error.code).toBe('rate_limited');
    });

    it('explains an expired session instead of failing opaquely', async () => {
        stubFetch([{ status: 419, body: {} }]);

        const result = await tools.search_products.execute({});

        expect(payloadOf(result).error.code).toBe('session_expired');
    });

    it('survives a response that is not json', async () => {
        stubFetch([{ status: 500, invalidJson: true }]);

        const result = await tools.search_products.execute({});

        expect(result.isError).toBe(true);
        expect(payloadOf(result).error.message).toContain('500');
    });

    it('surfaces a validation message from the server', async () => {
        stubFetch([{ status: 422, body: { message: 'The sort field is invalid.' } }]);

        const result = await tools.search_products.execute({ sort: 'nonsense' });

        expect(payloadOf(result).error.message).toBe('The sort field is invalid.');
    });
});
