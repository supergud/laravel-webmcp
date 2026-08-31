import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { payloadOf, stubBrowser, stubFetch } from './helpers.js';

let tools;
let browser;

async function loadTools() {
    const module = await import('../../resources/js/webmcp/tools/cart.js?t=' + Math.random());

    return Object.fromEntries(module.cartTools.map((tool) => [tool.name, tool]));
}

beforeEach(async () => {
    browser = stubBrowser();
    tools = await loadTools();
});

afterEach(() => {
    vi.unstubAllGlobals();
    vi.resetModules();
});

describe('get_cart', () => {
    it('reads the cart without changing anything', async () => {
        const body = { ok: true, cart: { items: [], total: 0 } };
        const { calls } = stubFetch([{ body }]);

        const result = await tools.get_cart.execute();

        expect(calls[0].init.method).toBe('GET');
        expect(payloadOf(result)).toEqual(body);
        // A read must not make the page re-render.
        expect(browser.livewire.dispatch).not.toHaveBeenCalled();
    });
});

describe('add_to_cart', () => {
    it('posts the sku and quantity', async () => {
        const { calls } = stubFetch([{ body: { ok: true } }]);

        await tools.add_to_cart.execute({ sku: 'LAP-1001', quantity: 2 });

        expect(calls[0].init.method).toBe('POST');
        expect(new URL(calls[0].url).pathname).toBe('/api/mcp/cart/items');
        expect(JSON.parse(calls[0].init.body)).toEqual({ sku: 'LAP-1001', quantity: 2 });
    });

    it('sends the csrf token on a write', async () => {
        const { calls } = stubFetch([{ body: { ok: true } }]);

        await tools.add_to_cart.execute({ sku: 'LAP-1001' });

        expect(calls[0].init.headers['X-CSRF-TOKEN']).toBe('test-csrf-token');
    });

    it('tells the page to re-render so the change is visible', async () => {
        stubFetch([{ body: { ok: true } }]);

        await tools.add_to_cart.execute({ sku: 'LAP-1001' });

        expect(browser.livewire.dispatch).toHaveBeenCalledWith('cart-updated', {});
    });

    it('reports a rejected limit as a readable tool error', async () => {
        stubFetch([
            {
                status: 422,
                body: {
                    ok: false,
                    error: { code: 'rejected', message: 'You can order at most 10 of any single product.' },
                },
            },
        ]);

        const result = await tools.add_to_cart.execute({ sku: 'LAP-1001', quantity: 99 });

        expect(result.isError).toBe(true);
        expect(payloadOf(result).error.message).toContain('at most 10');
    });

    it('still refreshes the page after a rejection so the view matches the server', async () => {
        stubFetch([{ status: 422, body: { error: { message: 'nope' } } }]);

        await tools.add_to_cart.execute({ sku: 'LAP-1001', quantity: 99 });

        expect(browser.livewire.dispatch).toHaveBeenCalledWith('cart-updated', {});
    });
});

describe('update_cart_item', () => {
    it('patches the addressed line', async () => {
        const { calls } = stubFetch([{ body: { ok: true } }]);

        await tools.update_cart_item.execute({ sku: 'LAP-1001', quantity: 3 });

        expect(calls[0].init.method).toBe('PATCH');
        expect(new URL(calls[0].url).pathname).toBe('/api/mcp/cart/items/LAP-1001');
        expect(JSON.parse(calls[0].init.body)).toEqual({ quantity: 3 });
    });

    it('accepts zero, which the server treats as removal', async () => {
        const { calls } = stubFetch([{ body: { ok: true } }]);

        await tools.update_cart_item.execute({ sku: 'LAP-1001', quantity: 0 });

        expect(JSON.parse(calls[0].init.body)).toEqual({ quantity: 0 });
        expect(tools.update_cart_item.inputSchema.properties.quantity.minimum).toBe(0);
    });

    it('escapes a sku that would otherwise change the path', async () => {
        const { calls } = stubFetch([{ body: {} }]);

        await tools.update_cart_item.execute({ sku: '../../../cart', quantity: 1 });

        expect(new URL(calls[0].url).pathname).toBe('/api/mcp/cart/items/..%2F..%2F..%2Fcart');
    });
});

describe('remove_from_cart and clear_cart', () => {
    it('deletes one line', async () => {
        const { calls } = stubFetch([{ body: { ok: true } }]);

        await tools.remove_from_cart.execute({ sku: 'LAP-1001' });

        expect(calls[0].init.method).toBe('DELETE');
        expect(new URL(calls[0].url).pathname).toBe('/api/mcp/cart/items/LAP-1001');
    });

    it('deletes the whole cart', async () => {
        const { calls } = stubFetch([{ body: { ok: true } }]);

        await tools.clear_cart.execute();

        expect(calls[0].init.method).toBe('DELETE');
        expect(new URL(calls[0].url).pathname).toBe('/api/mcp/cart');
        expect(browser.livewire.dispatch).toHaveBeenCalledWith('cart-updated', {});
    });

    it('warns in its description that clearing throws everything away', () => {
        expect(tools.clear_cart.description.toLowerCase()).toContain('confirm');
    });
});

describe('when livewire is absent', () => {
    it('still completes the write without throwing', async () => {
        vi.unstubAllGlobals();
        vi.resetModules();
        stubBrowser({ livewire: false });
        tools = await loadTools();
        stubFetch([{ body: { ok: true } }]);

        const result = await tools.add_to_cart.execute({ sku: 'LAP-1001' });

        expect(result.isError).toBeUndefined();
    });
});
