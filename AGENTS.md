# AGENTS.md

Notes for a coding agent working on this repository.

## What this project is

A Laravel 13 + Livewire 4 demo storefront that exposes its features to browser
AI agents through the WebMCP `document.modelContext` API. It exists to
demonstrate WebMCP, so the tool surface in `resources/js/webmcp/` and the
reasoning in `docs/webmcp-tools.md` are the point of the repository, not
incidental to it.

## Running things

Requires **PHP 8.3+**, because the demo host serves 8.3. `config.platform.php`
in `composer.json` pins resolution to 8.3, which is what keeps Laravel 13 on
Symfony 7.4 and Pest on 4. **Do not remove that pin**: without it a
`composer update` from an 8.4/8.5 machine pulls Symfony 8 (`>= 8.4.1`) back in,
and the resulting lock fails the host's `platform_check` on install.

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite          # gitignored; migrate will not create it
php artisan migrate --seed
npm run build
```

| Task | Command |
| --- | --- |
| PHP tests | `php artisan test` (Pest 4 on PHPUnit 12) |
| JavaScript tests | `npm test` (Vitest, via `vp test`) |
| Formatting | `vendor/bin/pint` |
| Static analysis | `vendor/bin/phpstan analyse` (level 7, must stay at zero errors) |

Run all four before proposing a change. CI runs the same set on PHP 8.3, 8.4
and 8.5.

### On this developer's machine (ServBay, Windows)

The `php` on `PATH` is an old XAMPP build and will not run this project. Use
ServBay's binary directly:

```
/c/ServBay/packages/php/8.5/php.exe
/c/ServBay/packages/php/8.3/php.exe    # matches the demo host
/c/ServBay/packages/composer/composer
```

Anything that touches dependencies or PHP-version-sensitive syntax should be
re-run under 8.3 as well, since that is the version the demo is served on.

`composer run <script>` resolves its own PHP and may pick a different version;
invoke `vendor/bin/*` tools with the 8.5 binary directly instead.

The site is served by ServBay at `https://laravel-webmcp.local` (nginx vhost,
docroot `public/`, PHP-FPM on port 9085). **It must stay HTTPS**: WebMCP is
gated behind `SecureContext`, so the API does not exist on a plain-HTTP origin.

## Conventions

- **Livewire 4 single-file components.** Files live in
  `resources/views/pages/` and `resources/views/livewire/`, prefixed with `⚡`,
  written as `new class extends Component { ... }; ?>` followed by the
  template. Full-page components are routed with `Route::livewire()`, not
  `Route::get()`.
- **Business logic lives in `app/Services/`.** Livewire components and the
  `/api/mcp/*` controllers are both thin callers. This is deliberate: it is
  what guarantees a person and an agent hit identical rules. Do not add a rule
  to a controller or a component that is not in a service.
- **UI strings go through `__()`.** `lang/{en,zh-TW}/shop.php` for shop copy,
  `lang/zh-TW.json` for the starter kit's English-keyed strings. Never hardcode
  user-visible text.
- **WebMCP tool names, descriptions and schemas stay English**, whatever the
  page language. They are read by a model, not a person. The data the tools
  return is localized.
- Money is a whole-TWD integer everywhere. No floats.
- Translatable model text uses `spatie/laravel-translatable` (JSON columns).

## Gotchas that have already bitten

- **Do not name a route parameter after a component property typed as a
  model.** `Route::livewire('products/{product}', ...)` with a
  `public Product $product` makes Livewire try to resolve it as a model and
  404 before `mount()` runs. The parameter is `{slug}` for this reason.
- **PHPStan runs single-process** (`parallel.maximumNumberOfProcesses: 1` in
  `phpstan.neon`). Its parallel workers intermittently fail to run larastan's
  bootstrap on Windows, leaving `LARAVEL_VERSION` undefined and aborting the
  run. Do not remove this without checking it repeatedly.
- **Vitest comes from `vite-plus`**, which already bundles it. Installing
  `vitest` separately produces an unresolvable peer conflict.
- **JS tests run in the `node` environment with hand-written stubs**, not
  jsdom. `document.modelContext` only exists in Chrome behind a flag, so no DOM
  simulator can provide the thing under test; stubbing by hand keeps the exact
  browser surface visible in `tests/js/helpers.js`.
- Rate limiters are keyed by account or IP, **never by session id** — a client
  that declines the session cookie gets a fresh id per request, so a
  session-derived key throttles nothing.

## Things not to do

- **Do not add a tool that confirms or places an order.** The absence is the
  security design; see `docs/webmcp-tools.md`. Tests in
  `tests/Feature/Mcp/OrderApiTest.php` and `tests/js/order-tools.test.js`
  assert it stays absent.
- **Do not add a login or register tool.** Same reason.
- **Do not let the WebMCP layer log at error level or render UI** when
  `modelContext` is missing. A browser without the flag is an expected state,
  and this is used for live demos where a red console is a distraction. Debug
  output is gated behind the `webmcp-debug` meta tag, emitted only when
  `APP_DEBUG` is true.
- Do not commit `.env` or `database/database.sqlite`.
- Do not weaken a limit in `config/shop.php` to make a test pass.
