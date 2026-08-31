import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { payloadOf, stubBrowser, stubFetch } from './helpers.js';

let tool;
let browser;

async function loadTool(options = {}) {
    browser = stubBrowser(options);

    const module = await import('../../resources/js/webmcp/tools/locale.js?t=' + Math.random());

    return module.localeTools[0];
}

beforeEach(async () => {
    tool = await loadTool({ pathname: '/en/products/aerobook-pro-14' });
});

afterEach(() => {
    vi.unstubAllGlobals();
    vi.resetModules();
});

describe('set_locale', () => {
    it('sends the locale and the current path', async () => {
        const { calls } = stubFetch([{ body: { url: '/zh-TW' } }]);

        await tool.execute({ locale: 'zh-TW' });

        expect(new URL(calls[0].url).pathname).toBe('/api/mcp/locale');
        expect(JSON.parse(calls[0].init.body)).toEqual({
            locale: 'zh-TW',
            path: '/en/products/aerobook-pro-14',
        });
    });

    it('navigates to the url the server built', async () => {
        stubFetch([{ body: { url: 'https://laravel-webmcp.local/zh-TW/products/aerobook-pro-14' } }]);

        await tool.execute({ locale: 'zh-TW' });

        expect(browser.livewire.navigate).toHaveBeenCalledWith(
            'https://laravel-webmcp.local/zh-TW/products/aerobook-pro-14',
        );
    });

    it('never navigates anywhere the server did not name', async () => {
        // Even if a model supplies a url-shaped locale, the destination comes
        // only from the server's response.
        stubFetch([{ body: { url: 'https://laravel-webmcp.local/zh-TW' } }]);

        await tool.execute({ locale: 'https://evil.example.com' });

        expect(browser.livewire.navigate).toHaveBeenCalledWith('https://laravel-webmcp.local/zh-TW');
        expect(browser.window.location.assign).not.toHaveBeenCalled();
    });

    it('falls back to a full page load when livewire is unavailable', async () => {
        vi.unstubAllGlobals();
        vi.resetModules();
        tool = await loadTool({ livewire: false, pathname: '/en' });
        stubFetch([{ body: { url: 'https://laravel-webmcp.local/zh-TW' } }]);

        await tool.execute({ locale: 'zh-TW' });

        expect(browser.window.location.assign).toHaveBeenCalledWith('https://laravel-webmcp.local/zh-TW');
    });

    it('does not navigate when the switch failed', async () => {
        stubFetch([{ status: 422, body: { message: 'The selected locale is invalid.' } }]);

        const result = await tool.execute({ locale: 'fr' });

        expect(result.isError).toBe(true);
        expect(payloadOf(result).error.message).toContain('locale');
        expect(browser.livewire.navigate).not.toHaveBeenCalled();
    });

    it('publishes exactly the locales the shop serves', () => {
        expect(tool.inputSchema.properties.locale.enum).toEqual(['en', 'zh-TW']);
        expect(tool.inputSchema.required).toEqual(['locale']);
    });
});
