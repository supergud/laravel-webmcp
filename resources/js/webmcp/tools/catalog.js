import { get } from '../client.js';
import { toolError, toolResult } from '../support.js';
import { ApiError } from '../client.js';

/**
 * Read-only catalogue tools.
 *
 * Tool names and descriptions are in English regardless of the page language:
 * they are read by a model choosing between tools, not by a person. The data
 * that comes back is localised, so asking in Chinese returns Chinese product
 * names.
 */

const SORTS = ['newest', 'price_asc', 'price_desc', 'name'];

async function call(work) {
    try {
        return toolResult(await work());
    } catch (error) {
        if (error instanceof ApiError) {
            return toolError(error.message, error.code);
        }

        return toolError('The shop could not be reached.', 'network_error');
    }
}

export const catalogTools = [
    {
        name: 'search_products',
        description:
            'Search this shop\'s catalogue of consumer electronics. Returns matching products with their SKU, ' +
            'localized name and description, price in New Taiwan dollars, stock availability and a link to the ' +
            'product page. Every filter is optional; call with no arguments to browse everything. Product names ' +
            'and descriptions come back in the language the shop is currently displaying.',
        inputSchema: {
            type: 'object',
            properties: {
                term: {
                    type: 'string',
                    description:
                        'Free text matched against the product name, description and SKU in the current language.',
                },
                category: {
                    type: 'string',
                    description: 'Restrict to one category, given as the slug returned by list_categories.',
                },
                min_price: {
                    type: 'integer',
                    minimum: 0,
                    description: 'Lowest acceptable price, in whole New Taiwan dollars.',
                },
                max_price: {
                    type: 'integer',
                    minimum: 0,
                    description: 'Highest acceptable price, in whole New Taiwan dollars.',
                },
                sort: {
                    type: 'string',
                    enum: SORTS,
                    description: 'Result ordering. Defaults to newest.',
                },
                page: { type: 'integer', minimum: 1, description: 'Page number, starting at 1.' },
                per_page: {
                    type: 'integer',
                    minimum: 1,
                    maximum: 48,
                    description: 'Results per page, at most 48. Defaults to 12.',
                },
            },
            required: [],
            additionalProperties: false,
        },
        execute: (args = {}) => call(() => get('products', args)),
    },

    {
        name: 'get_product',
        description:
            'Fetch one product from this shop by its SKU (for example "LAP-1001") or by its URL slug. Use this ' +
            'after search_products when you need the full description or want to confirm a price before adding ' +
            'the product to the cart.',
        inputSchema: {
            type: 'object',
            properties: {
                identifier: {
                    type: 'string',
                    description: 'The product SKU or URL slug.',
                },
            },
            required: ['identifier'],
            additionalProperties: false,
        },
        execute: ({ identifier } = {}) =>
            call(() => get(`products/${encodeURIComponent(identifier ?? '')}`)),
    },

    {
        name: 'list_categories',
        description:
            'List the product categories in this shop, with the localized category name and how many products ' +
            'each one currently has. Use the returned slug as the "category" argument to search_products.',
        inputSchema: {
            type: 'object',
            properties: {},
            required: [],
            additionalProperties: false,
        },
        execute: () => call(() => get('categories')),
    },
];
