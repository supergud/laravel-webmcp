import { debug } from './support.js';

/**
 * Tell the page that server-side state changed.
 *
 * This is the piece that makes an agent's action visible. The tools write
 * through the JSON API rather than by driving Livewire directly - one write
 * path, easy to test, and it does not care which component happens to be on
 * screen - so nothing would re-render on its own. Dispatching the same event
 * the UI dispatches after its own writes lets the cart badge, the cart page
 * and the checkout page re-read their state and update in place.
 *
 * Livewire may legitimately be absent (a page that renders no components), so
 * this never throws.
 *
 * @param {string} event
 * @param {object} payload
 */
export function notifyPage(event, payload = {}) {
    const livewire = typeof window === 'undefined' ? null : window.Livewire;

    if (!livewire || typeof livewire.dispatch !== 'function') {
        debug('livewire not present, skipping', event);

        return;
    }

    try {
        livewire.dispatch(event, payload);
        debug('dispatched', event);
    } catch (error) {
        debug('could not dispatch', event, error?.name ?? error);
    }
}
