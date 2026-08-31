import { afterEach, describe, expect, it, vi } from 'vitest';
import { stubBrowser } from './helpers.js';

afterEach(() => {
    vi.unstubAllGlobals();
    vi.resetModules();
    vi.restoreAllMocks();
});

async function loadEntry() {
    return import('../../resources/js/webmcp/index.js?t=' + Math.random());
}

describe('model context detection', () => {
    it('prefers document.modelContext, the current location', async () => {
        const browser = stubBrowser({ modelContextOn: 'document' });
        const { resolveModelContext } = await import('../../resources/js/webmcp/support.js?t=' + Math.random());

        expect(resolveModelContext()).toBe(browser.modelContext);
    });

    it('falls back to navigator.modelContext, where Chrome 146 shipped it', async () => {
        const browser = stubBrowser({ modelContextOn: 'navigator' });
        const { resolveModelContext } = await import('../../resources/js/webmcp/support.js?t=' + Math.random());

        expect(resolveModelContext()).toBe(browser.modelContext);
    });

    it('returns null when the browser has neither', async () => {
        stubBrowser({ modelContextOn: null });
        const { resolveModelContext } = await import('../../resources/js/webmcp/support.js?t=' + Math.random());

        expect(resolveModelContext()).toBeNull();
    });
});

describe('registration', () => {
    it('offers every tool to the browser', async () => {
        const browser = stubBrowser();

        const { tools } = await loadEntry();
        await vi.waitFor(() => expect(browser.registered.length).toBe(tools.length));

        expect(browser.registered.map((tool) => tool.name).sort()).toEqual(
            tools.map((tool) => tool.name).sort(),
        );
    });

    it('does nothing and stays silent when WebMCP is unavailable', async () => {
        stubBrowser({ modelContextOn: null });

        const error = vi.spyOn(console, 'error').mockImplementation(() => {});
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
        const info = vi.spyOn(console, 'info').mockImplementation(() => {});

        await expect(loadEntry()).resolves.toBeDefined();

        expect(error).not.toHaveBeenCalled();
        expect(warn).not.toHaveBeenCalled();
        // Debug logging is opt-in, and the debug meta tag is absent here.
        expect(info).not.toHaveBeenCalled();
    });

    it('logs at info level only when the debug meta tag is present', async () => {
        stubBrowser({ modelContextOn: null, debug: true });

        const info = vi.spyOn(console, 'info').mockImplementation(() => {});
        const error = vi.spyOn(console, 'error').mockImplementation(() => {});

        await loadEntry();

        expect(info).toHaveBeenCalled();
        expect(error).not.toHaveBeenCalled();
    });

    it('keeps going when one tool is rejected by the browser', async () => {
        const browser = stubBrowser();
        let first = true;

        browser.modelContext.registerTool.mockImplementation(async (tool) => {
            if (first) {
                first = false;
                throw new DOMException('duplicate', 'InvalidStateError');
            }

            browser.registered.push(tool);
        });

        const error = vi.spyOn(console, 'error').mockImplementation(() => {});

        const { tools } = await loadEntry();
        await vi.waitFor(() => expect(browser.registered.length).toBe(tools.length - 1));

        expect(error).not.toHaveBeenCalled();
    });

    it('registers only once even if invoked again', async () => {
        const browser = stubBrowser();

        const { tools, registerTools } = await loadEntry();
        await vi.waitFor(() => expect(browser.registered.length).toBe(tools.length));

        await registerTools();

        expect(browser.modelContext.registerTool).toHaveBeenCalledTimes(tools.length);
    });
});

describe('tool declarations', () => {
    it('declares each tool with everything an agent needs to choose it', async () => {
        stubBrowser();
        const { tools } = await loadEntry();

        for (const tool of tools) {
            expect(tool.name, 'tool name').toMatch(/^[a-z][a-z0-9_]*$/);
            expect(tool.description.length, `${tool.name} description`).toBeGreaterThan(40);
            expect(tool.inputSchema.type).toBe('object');
            expect(tool.inputSchema.additionalProperties).toBe(false);
            expect(typeof tool.execute).toBe('function');
        }
    });

    it('gives every tool a unique name', async () => {
        stubBrowser();
        const { tools } = await loadEntry();

        expect(new Set(tools.map((tool) => tool.name)).size).toBe(tools.length);
    });

    it('keeps tool descriptions in English, whatever language the page is in', async () => {
        stubBrowser();
        const { tools } = await loadEntry();

        for (const tool of tools) {
            const text = tool.name + tool.description + JSON.stringify(tool.inputSchema);

            // Model-facing text is deliberately English only; the localized
            // strings belong in the data the tools return.
            expect(/[㐀-鿿]/u.test(text), `${tool.name} must not contain CJK`).toBe(false);
        }
    });

    it('marks required arguments in every schema that has them', async () => {
        stubBrowser();
        const { tools } = await loadEntry();

        for (const tool of tools) {
            expect(Array.isArray(tool.inputSchema.required)).toBe(true);

            for (const name of tool.inputSchema.required) {
                expect(tool.inputSchema.properties).toHaveProperty(name);
            }
        }
    });

    it('documents every declared property', async () => {
        stubBrowser();
        const { tools } = await loadEntry();

        for (const tool of tools) {
            for (const [name, schema] of Object.entries(tool.inputSchema.properties)) {
                expect(schema.description, `${tool.name}.${name}`).toBeTruthy();
            }
        }
    });
});
