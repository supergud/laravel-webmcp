/**
 * The HTTP client the WebMCP tools use to reach this application.
 *
 * Every request is same-origin and carries the visitor's own cookies, which is
 * the whole security model: an agent calling these tools is the signed-in
 * person, with their permissions and no others. There is no API key, no bearer
 * token and no way for a tool to widen its own access.
 */

const BASE_PATH = '/api/mcp/';

export class ApiError extends Error {
    constructor(message, code, status) {
        super(message);
        this.name = 'ApiError';
        this.code = code;
        this.status = status;
    }
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function buildUrl(path, query) {
    const url = new URL(BASE_PATH + path, window.location.origin);

    for (const [key, value] of Object.entries(query ?? {})) {
        if (value !== undefined && value !== null && value !== '') {
            url.searchParams.set(key, String(value));
        }
    }

    return url;
}

/**
 * @param {string} method
 * @param {string} path
 * @param {{query?: object, body?: object}} options
 */
export async function request(method, path, options = {}) {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    const init = { method, headers, credentials: 'same-origin' };

    if (options.body !== undefined) {
        headers['Content-Type'] = 'application/json';
        // State-changing tool calls are ordinary form posts as far as the
        // server is concerned, and are CSRF-protected like any other.
        headers['X-CSRF-TOKEN'] = csrfToken();
        init.body = JSON.stringify(options.body);
    }

    const response = await fetch(buildUrl(path, options.query), init);

    let data = null;

    try {
        data = await response.json();
    } catch {
        data = null;
    }

    if (!response.ok) {
        if (response.status === 429) {
            throw new ApiError('Too many requests. Please slow down.', 'rate_limited', 429);
        }

        if (response.status === 419) {
            throw new ApiError('The page session expired. Reload the page and try again.', 'session_expired', 419);
        }

        // Laravel returns 422 with a `message` and `errors` for validation.
        const message =
            data?.error?.message ??
            data?.message ??
            `Request failed with status ${response.status}.`;

        throw new ApiError(message, data?.error?.code ?? 'request_failed', response.status);
    }

    return data ?? {};
}

export const get = (path, query) => request('GET', path, { query });
export const post = (path, body) => request('POST', path, { body: body ?? {} });
export const patch = (path, body) => request('PATCH', path, { body: body ?? {} });
export const destroy = (path, body) => request('DELETE', path, { body: body ?? {} });
