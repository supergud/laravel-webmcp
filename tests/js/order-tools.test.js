import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { payloadOf, stubBrowser, stubFetch } from './helpers.js';

let tools;
let browser;

async function loadTools(options = {}) {
    browser = stubBrowser(options);

    const module = await import('../../resources/js/webmcp/tools/orders.js?t=' + Math.random());

    return Object.fromEntries(module.orderTools.map((tool) => [tool.name, tool]));
}

beforeEach(async () => {
    tools = await loadTools();
});

afterEach(() => {
    vi.unstubAllGlobals();
    vi.resetModules();
});

describe('the confirmation boundary', () => {
    it('ships no tool that can confirm or place an order', async () => {
        const entry = await import('../../resources/js/webmcp/index.js?t=' + Math.random());

        const names = entry.tools.map((tool) => tool.name);

        for (const forbidden of ['confirm_order', 'place_order', 'submit_order', 'pay', 'checkout']) {
            expect(names).not.toContain(forbidden);
        }

        expect(names.some((name) => name.includes('confirm'))).toBe(false);
    });

    it('ships no tool that can sign anybody in', async () => {
        const entry = await import('../../resources/js/webmcp/index.js?t=' + Math.random());

        const names = entry.tools.map((tool) => tool.name);

        for (const forbidden of ['login', 'log_in', 'sign_in', 'register', 'authenticate']) {
            expect(names).not.toContain(forbidden);
        }
    });

    it('tells the model plainly that it cannot confirm', () => {
        expect(tools.prepare_checkout.description).toContain('does NOT place the order');
        expect(tools.prepare_checkout.description).toContain('no tool that can confirm');
    });

    it('offers exactly thirteen tools', async () => {
        const entry = await import('../../resources/js/webmcp/index.js?t=' + Math.random());

        expect(entry.tools).toHaveLength(13);
    });
});

describe('prepare_checkout', () => {
    it('posts the shipping details', async () => {
        const { calls } = stubFetch([{ body: { ok: true, confirmation_url: '/en/checkout' } }]);

        await tools.prepare_checkout.execute({ shipping_address: 'No. 1, Somewhere Road' });

        expect(calls[0].init.method).toBe('POST');
        expect(new URL(calls[0].url).pathname).toBe('/api/mcp/checkout/prepare');
        expect(JSON.parse(calls[0].init.body)).toEqual({ shipping_address: 'No. 1, Somewhere Road' });
    });

    it('takes the customer to the confirmation page', async () => {
        stubFetch([{ body: { ok: true, confirmation_url: 'https://laravel-webmcp.local/en/checkout' } }]);

        await tools.prepare_checkout.execute({ shipping_address: 'Somewhere' });

        expect(browser.livewire.navigate).toHaveBeenCalledWith('https://laravel-webmcp.local/en/checkout');
    });

    it('refreshes the page so an open checkout view shows the draft', async () => {
        stubFetch([{ body: { ok: true, confirmation_url: '/en/checkout' } }]);

        await tools.prepare_checkout.execute({ shipping_address: 'Somewhere' });

        expect(browser.livewire.dispatch).toHaveBeenCalledWith('checkout-updated', {});
    });

    it('does not navigate when preparing failed', async () => {
        stubFetch([{ status: 422, body: { error: { code: 'rejected', message: 'Your cart is empty.' } } }]);

        const result = await tools.prepare_checkout.execute({ shipping_address: 'Somewhere' });

        expect(result.isError).toBe(true);
        expect(browser.livewire.navigate).not.toHaveBeenCalled();
    });

    it('requires a shipping address in its schema', () => {
        expect(tools.prepare_checkout.inputSchema.required).toEqual(['shipping_address']);
    });

    it('explains that the customer must sign in first, without offering to do it', async () => {
        stubFetch([{ status: 401, body: { message: 'Unauthenticated.' } }]);

        const result = await tools.prepare_checkout.execute({ shipping_address: 'Somewhere' });

        const payload = payloadOf(result);

        expect(payload.error.code).toBe('login_required');
        expect(payload.error.message).toContain('no tool that can sign anybody in');
    });
});

describe('order reads', () => {
    it('lists orders', async () => {
        const { calls } = stubFetch([{ body: { orders: [] } }]);

        await tools.list_orders.execute();

        expect(new URL(calls[0].url).pathname).toBe('/api/mcp/orders');
    });

    it('fetches one order by number', async () => {
        const { calls } = stubFetch([{ body: { order: {} } }]);

        await tools.get_order.execute({ number: 'ORD-20260901-A1B2C3' });

        expect(new URL(calls[0].url).pathname).toBe('/api/mcp/orders/ORD-20260901-A1B2C3');
    });

    it('escapes an order number that would otherwise change the path', async () => {
        const { calls } = stubFetch([{ body: {} }]);

        await tools.get_order.execute({ number: '../../cart' });

        expect(new URL(calls[0].url).pathname).toBe('/api/mcp/orders/..%2F..%2Fcart');
    });

    it('reads checkout status without changing anything', async () => {
        const { calls } = stubFetch([{ body: { awaiting_confirmation: true } }]);

        await tools.get_checkout_status.execute();

        expect(calls[0].init.method).toBe('GET');
        expect(browser.livewire.dispatch).not.toHaveBeenCalled();
        expect(browser.livewire.navigate).not.toHaveBeenCalled();
    });
});
