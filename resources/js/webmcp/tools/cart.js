import { ApiError, destroy, get, patch, post } from '../client.js';
import { notifyPage } from '../livewire.js';
import { toolError, toolResult } from '../support.js';

/**
 * Cart tools - the first ones that change anything.
 *
 * Each write notifies the page afterwards, so the header badge and any open
 * cart or checkout view re-read their state. That is what makes an agent's
 * action visible: ask it to add something and the cart count moves.
 *
 * The server enforces every limit again, so a rejection here is a normal
 * outcome and comes back as a readable message the agent can act on rather
 * than as an exception.
 */

async function call(work, { notify } = {}) {
    try {
        const payload = await work();

        if (notify) {
            notifyPage(notify);
        }

        return toolResult(payload);
    } catch (error) {
        if (error instanceof ApiError) {
            // A rejected write may still have changed nothing; refresh the page
            // anyway so what it shows matches what the server actually holds.
            if (notify) {
                notifyPage(notify);
            }

            return toolError(error.message, error.code);
        }

        return toolError('The shop could not be reached.', 'network_error');
    }
}

const CART_UPDATED = 'cart-updated';

export const cartTools = [
    {
        name: 'get_cart',
        description:
            "Read the current contents of the shopping cart in this browser session, with each line's SKU, " +
            'name, unit price, quantity and line total, plus the cart total in New Taiwan dollars and the ' +
            'limits the shop enforces. The cart belongs to the person using this browser; it is never anybody ' +
            "else's cart.",
        inputSchema: { type: 'object', properties: {}, required: [], additionalProperties: false },
        execute: () => call(() => get('cart')),
    },

    {
        name: 'add_to_cart',
        description:
            'Add a product to the shopping cart by SKU. If the product is already in the cart the quantity is ' +
            'added to what is there. The shop caps how many of one product a cart may hold, how many different ' +
            'products it may hold and what it may total; exceeding any of those returns an error explaining ' +
            'which limit was hit, and changes nothing.',
        inputSchema: {
            type: 'object',
            properties: {
                sku: { type: 'string', description: 'The product SKU, for example "LAP-1001".' },
                quantity: {
                    type: 'integer',
                    minimum: 1,
                    description: 'How many units to add. Defaults to 1.',
                },
            },
            required: ['sku'],
            additionalProperties: false,
        },
        execute: ({ sku, quantity } = {}) =>
            call(() => post('cart/items', { sku, quantity }), { notify: CART_UPDATED }),
    },

    {
        name: 'update_cart_item',
        description:
            'Set the exact quantity of one product already in the cart, addressed by SKU. A quantity of 0 ' +
            'removes the line. Use this rather than add_to_cart when the customer names a total quantity ' +
            'rather than an amount to add.',
        inputSchema: {
            type: 'object',
            properties: {
                sku: { type: 'string', description: 'The SKU of the cart line to change.' },
                quantity: {
                    type: 'integer',
                    minimum: 0,
                    description: 'The quantity the line should end up at. 0 removes it.',
                },
            },
            required: ['sku', 'quantity'],
            additionalProperties: false,
        },
        execute: ({ sku, quantity } = {}) =>
            call(() => patch(`cart/items/${encodeURIComponent(sku ?? '')}`, { quantity }), {
                notify: CART_UPDATED,
            }),
    },

    {
        name: 'remove_from_cart',
        description: 'Remove one product entirely from the shopping cart, addressed by SKU.',
        inputSchema: {
            type: 'object',
            properties: {
                sku: { type: 'string', description: 'The SKU of the cart line to remove.' },
            },
            required: ['sku'],
            additionalProperties: false,
        },
        execute: ({ sku } = {}) =>
            call(() => destroy(`cart/items/${encodeURIComponent(sku ?? '')}`), { notify: CART_UPDATED }),
    },

    {
        name: 'clear_cart',
        description:
            'Empty the shopping cart completely. This throws away every line at once, so confirm with the ' +
            'customer before calling it.',
        inputSchema: { type: 'object', properties: {}, required: [], additionalProperties: false },
        execute: () => call(() => destroy('cart'), { notify: CART_UPDATED }),
    },
];
