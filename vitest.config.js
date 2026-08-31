import { defineConfig } from 'vitest/config';

/*
 * The WebMCP layer is tested in the plain node environment with explicit
 * stubs rather than under a simulated DOM.
 *
 * navigator.modelContext only exists in Chrome with an experimental flag
 * enabled, so no DOM simulator can provide the thing actually under test.
 * Stubbing by hand keeps the exact browser surface this code depends on -
 * document.querySelector, fetch, window.location - visible in the tests, and
 * keeps CI free of a DOM dependency that would be simulating the wrong browser
 * anyway.
 *
 * These tests prove the tools are declared and wired correctly. They cannot
 * prove Chrome accepts them; only the extension can do that, by hand.
 */
export default defineConfig({
    test: {
        include: ['tests/js/**/*.test.js'],
        environment: 'node',
        globals: false,
    },
});
