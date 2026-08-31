import { ApiError, post } from '../client.js';
import { toolError, toolResult } from '../support.js';

/**
 * Switching the shop's language.
 *
 * This is the one tool that navigates. Storefront URLs carry the locale in the
 * path, so changing language means going to a different URL - and the whole
 * point of the tool is that the visitor watches the page turn into their
 * language, so it takes them there rather than only setting a preference.
 *
 * The destination is built by the server from the current path and never from
 * anything the model supplied, so a tool call cannot navigate the visitor off
 * this site.
 */

function navigate(url) {
    if (typeof window === 'undefined' || !url) {
        return;
    }

    // Livewire's SPA navigation keeps the page smooth where it is available.
    if (window.Livewire?.navigate) {
        window.Livewire.navigate(url);

        return;
    }

    window.location.assign(url);
}

export const localeTools = [
    {
        name: 'set_locale',
        description:
            'Switch the language the shop is displayed in and navigate to the current page in that language. ' +
            'Supported locales are "en" (English) and "zh-TW" (Traditional Chinese). After switching, product ' +
            'names, descriptions and category names returned by the other tools come back in the new language. ' +
            'Use this when the customer asks to see the site in a different language.',
        inputSchema: {
            type: 'object',
            properties: {
                locale: {
                    type: 'string',
                    enum: ['en', 'zh-TW'],
                    description: 'The locale to switch to.',
                },
            },
            required: ['locale'],
            additionalProperties: false,
        },
        async execute({ locale } = {}) {
            try {
                const payload = await post('locale', {
                    locale,
                    // The server rewrites only the locale segment of this path.
                    path: typeof window === 'undefined' ? null : window.location.pathname,
                });

                navigate(payload.url);

                return toolResult(payload);
            } catch (error) {
                if (error instanceof ApiError) {
                    return toolError(error.message, error.code);
                }

                return toolError('The shop could not be reached.', 'network_error');
            }
        },
    },
];
