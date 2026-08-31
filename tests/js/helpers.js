import { vi } from 'vitest';

/**
 * The minimal browser surface the WebMCP layer touches.
 *
 * @param {{modelContextOn?: 'document'|'navigator'|null, debug?: boolean, csrf?: string}} options
 */
export function stubBrowser(options = {}) {
    const { modelContextOn = 'document', debug = false, csrf = 'test-csrf-token' } = options;

    const registered = [];

    const modelContext = {
        registerTool: vi.fn(async (tool) => {
            registered.push(tool);
        }),
        unregisterTool: vi.fn(async () => {}),
    };

    const metas = {
        'meta[name="csrf-token"]': csrf === null ? null : { getAttribute: () => csrf },
        'meta[name="webmcp-debug"]': debug ? {} : null,
    };

    const documentStub = {
        readyState: 'complete',
        addEventListener: vi.fn(),
        querySelector: (selector) => metas[selector] ?? null,
    };

    const navigatorStub = {};

    if (modelContextOn === 'document') {
        documentStub.modelContext = modelContext;
    } else if (modelContextOn === 'navigator') {
        navigatorStub.modelContext = modelContext;
    }

    vi.stubGlobal('document', documentStub);
    vi.stubGlobal('navigator', navigatorStub);
    vi.stubGlobal('window', { location: { origin: 'https://laravel-webmcp.local' } });

    return { modelContext, registered, document: documentStub, navigator: navigatorStub };
}

/**
 * A fetch stub that records calls and replies with the queued responses.
 *
 * @param {Array<{status?: number, body?: unknown, invalidJson?: boolean}>} responses
 */
export function stubFetch(responses) {
    const calls = [];
    const queue = [...responses];

    const fetchStub = vi.fn(async (url, init) => {
        calls.push({ url: url.toString(), init });

        const next = queue.shift() ?? { status: 200, body: {} };
        const status = next.status ?? 200;

        return {
            ok: status >= 200 && status < 300,
            status,
            json: async () => {
                if (next.invalidJson) {
                    throw new SyntaxError('Unexpected token');
                }

                return next.body ?? {};
            },
        };
    });

    vi.stubGlobal('fetch', fetchStub);

    return { calls, fetchStub };
}

/**
 * Parse the JSON a tool returned in its text content block.
 */
export function payloadOf(result) {
    return JSON.parse(result.content[0].text);
}
