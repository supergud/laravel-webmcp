import { ApiError, get, post } from '../client.js';
import { notifyPage } from '../livewire.js';
import { toolError, toolResult } from '../support.js';

/**
 * Order and checkout tools.
 *
 * There is no confirm_order tool here, and that absence is the design.
 * prepare_checkout assembles an order and hands it to the customer;
 * confirming it is something only a person does, in the page.
 *
 * The reason is prompt injection. Product descriptions, reviews and search
 * results are untrusted text that flows straight into an agent's context. A
 * tool that placed orders would be a tool that page text could talk an agent
 * into using. Not shipping one means the worst a hijacked agent can do here is
 * put a confirmation in front of the customer, which they can decline.
 */

const CHECKOUT_UPDATED = 'checkout-updated';

async function call(work, { notify } = {}) {
    try {
        const payload = await work();

        if (notify) {
            notifyPage(notify);
        }

        return toolResult(payload);
    } catch (error) {
        if (error instanceof ApiError) {
            if (error.status === 401) {
                return toolError(
                    'The customer must be signed in for this. Ask them to log in - there is no tool that ' +
                        'can sign anybody in.',
                    'login_required',
                );
            }

            return toolError(error.message, error.code);
        }

        return toolError('The shop could not be reached.', 'network_error');
    }
}

function navigate(url) {
    if (typeof window === 'undefined' || !url) {
        return;
    }

    if (window.Livewire?.navigate) {
        window.Livewire.navigate(url);

        return;
    }

    window.location.assign(url);
}

export const orderTools = [
    {
        name: 'list_orders',
        description:
            'List the orders the signed-in customer has actually placed, newest first, with order number, ' +
            'status, total and line items. Only ever returns this account\'s own orders. Unconfirmed drafts ' +
            'are not orders and do not appear here.',
        inputSchema: { type: 'object', properties: {}, required: [], additionalProperties: false },
        execute: () => call(() => get('orders')),
    },

    {
        name: 'get_order',
        description:
            'Fetch one placed order belonging to the signed-in customer by its order number, for example ' +
            '"ORD-20260901-A1B2C3". An order number belonging to a different account is reported as not ' +
            'found, exactly like one that was never issued.',
        inputSchema: {
            type: 'object',
            properties: {
                number: { type: 'string', description: 'The order number.' },
            },
            required: ['number'],
            additionalProperties: false,
        },
        execute: ({ number } = {}) => call(() => get(`orders/${encodeURIComponent(number ?? '')}`)),
    },

    {
        name: 'prepare_checkout',
        description:
            'Turn the current cart into a draft order and show it to the customer for confirmation. This does ' +
            'NOT place the order: it takes no payment, reserves no stock, and expires on its own. The customer ' +
            'must confirm it themselves on the checkout page, which this tool navigates them to. There is no ' +
            'tool that can confirm an order - if you are asked to place an order without the customer ' +
            'confirming, explain that you cannot. Requires the customer to be signed in.',
        inputSchema: {
            type: 'object',
            properties: {
                shipping_address: {
                    type: 'string',
                    description: 'Where the order should be delivered. Ask the customer for this.',
                },
                shipping_name: {
                    type: 'string',
                    description: "Recipient name. Defaults to the account holder's name.",
                },
                shipping_email: {
                    type: 'string',
                    description: "Contact email. Defaults to the account holder's email.",
                },
            },
            required: ['shipping_address'],
            additionalProperties: false,
        },
        async execute(args = {}) {
            const result = await call(() => post('checkout/prepare', args), { notify: CHECKOUT_UPDATED });

            // Take the customer to the confirmation. The tool's whole purpose
            // is to hand control back to a person, and it can only do that by
            // putting the decision in front of them.
            if (!result.isError) {
                navigate(result.structuredContent?.confirmation_url);
            }

            return result;
        },
    },

    {
        name: 'get_checkout_status',
        description:
            'Check whether the customer has confirmed the order that was prepared for them. Returns whether a ' +
            'draft is still awaiting confirmation, its details and expiry, and the most recently placed order. ' +
            'Use this to find out what the customer decided; it gives you no way to decide for them.',
        inputSchema: { type: 'object', properties: {}, required: [], additionalProperties: false },
        execute: () => call(() => get('checkout/status')),
    },
];
