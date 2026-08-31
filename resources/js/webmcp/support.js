/**
 * Shared helpers for the WebMCP integration.
 */

/**
 * Resolve the browser's model context, whichever name it is published under.
 *
 * Chrome 146 shipped this as `navigator.modelContext`. It moved to
 * `document.modelContext` around Chrome 150, with the original kept as a
 * deprecated alias. Reading a missing property is not an error in either
 * place, so this probe is silent on browsers that have neither.
 *
 * @returns {object|null}
 */
export function resolveModelContext() {
    if (typeof document !== 'undefined' && document.modelContext) {
        return document.modelContext;
    }

    if (typeof navigator !== 'undefined' && navigator.modelContext) {
        return navigator.modelContext;
    }

    return null;
}

/**
 * Debug logging that is off unless the application is in debug mode.
 *
 * Nothing here ever logs at error level. A browser without WebMCP is an
 * expected state, not a fault, and a demo should not be reading a red console.
 */
const debugEnabled =
    typeof document !== 'undefined' &&
    document.querySelector('meta[name="webmcp-debug"]') !== null;

export function debug(...args) {
    if (debugEnabled) {
        console.info('[webmcp]', ...args);
    }
}

/**
 * A successful tool result.
 *
 * The payload is returned twice on purpose: as JSON text, which every MCP
 * client can read, and as `structuredContent` for clients that understand it.
 *
 * @param {object} payload
 */
export function toolResult(payload) {
    return {
        content: [{ type: 'text', text: JSON.stringify(payload) }],
        structuredContent: payload,
    };
}

/**
 * A failed tool result.
 *
 * Failures are returned as values rather than thrown, so the agent is told
 * what went wrong - "only 3 left in stock" is something it can act on - and
 * the page never surfaces an unhandled rejection.
 *
 * @param {string} message
 * @param {string} code
 */
export function toolError(message, code = 'error') {
    const payload = { ok: false, error: { code, message } };

    return {
        content: [{ type: 'text', text: JSON.stringify(payload) }],
        structuredContent: payload,
        isError: true,
    };
}
