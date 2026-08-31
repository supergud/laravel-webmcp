import { debug, resolveModelContext } from './support.js';
import { cartTools } from './tools/cart.js';
import { catalogTools } from './tools/catalog.js';
import { localeTools } from './tools/locale.js';

/**
 * Registers this shop's tools with the browser's model context.
 *
 * Tools are registered once, globally, rather than per page. Livewire's
 * wire:navigate swaps the DOM without re-running this module, and an agent
 * should be able to search the catalogue or read the cart from wherever the
 * visitor happens to be standing - not only from the page that happens to
 * show that feature.
 *
 * If the browser has no WebMCP support this does nothing at all: no throw, no
 * console error, no UI. On a browser without the flag enabled that is the
 * expected state, and a demo should not be reading a red console because of it.
 */

const tools = [...catalogTools, ...cartTools, ...localeTools];

let registered = false;

async function registerTools() {
    if (registered) {
        return;
    }

    const context = resolveModelContext();

    if (context === null) {
        debug(
            'No model context on this page. WebMCP needs Chrome 146 or newer with the ' +
                '"WebMCP for testing" flag enabled at chrome://flags, on a secure origin.',
        );

        return;
    }

    registered = true;

    for (const tool of tools) {
        try {
            await context.registerTool(tool);
            debug('registered', tool.name);
        } catch (error) {
            // A duplicate name or a rejected permission is not worth breaking
            // the page over; the remaining tools still register.
            debug('could not register', tool.name, error?.name ?? error);
        }
    }

    debug(`${tools.length} tool(s) offered to the browser`);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', registerTools, { once: true });
} else {
    registerTools();
}

export { tools, registerTools };
