<h1>Laravel WebMCP Shop</h1>

A small bilingual storefront built with **Laravel 13** and **Livewire 4** that
exposes every one of its features to browser AI agents through
[**WebMCP**](https://github.com/webmachinelearning/webmcp) — the
`document.modelContext` API that lets a page register callable tools for
whatever agent the visitor is running.

It is a demo, and it is built to demonstrate one specific thing well: **what a
responsible WebMCP tool surface looks like**, including the tools it
deliberately does not offer.

[![tests](https://github.com/supergud/laravel-webmcp/actions/workflows/tests.yml/badge.svg)](https://github.com/supergud/laravel-webmcp/actions/workflows/tests.yml)

---

## What it does

A shop: 43 consumer-electronics products in 6 categories, search and filtering,
a cart, accounts, checkout and order history — in English and Traditional
Chinese, with the language in the URL.

Thirteen WebMCP tools let an agent do all of it: search the catalogue, read and
change the cart, switch language, read order history and assemble an order.

**No AI chat is built into this site.** The agent is whatever the visitor
brings — a browser extension, a built-in assistant. That is the whole premise
of WebMCP, and adding a fake chat box would defeat the point of the
demonstration.

## The part worth looking at

Thirteen tools, and two conspicuous absences:

- **No tool can confirm or place an order.** `prepare_checkout` writes a draft
  — no payment, no stock reserved, expires in 30 minutes. Confirming it
  requires a token that exists only in the rendered checkout page, and only a
  person clicking in that page can do it.
- **No tool can sign anybody in.** Endpoints that need an account return 401,
  which the tools turn into "ask the customer to log in".

The reason is prompt injection. Product descriptions and reviews are untrusted
text that flows straight into an agent's context window. A tool that placed
orders would be a tool that *page text* could talk an agent into using.

The catalogue ships a product that tries exactly that — `ACC-5099` **Test
Device (Prompt Injection Sample)** — so you can point an agent at it and watch
the payload fail to go anywhere.

Full reasoning, the tool reference and the honest limits:
**[docs/webmcp-tools.md](docs/webmcp-tools.md)**.

---

## Requirements

| | |
| --- | --- |
| PHP | **8.3+** (the lock is pinned to 8.3 via `config.platform`, which holds Laravel 13 on Symfony 7.4) |
| Node | 22+ |
| Database | SQLite — no server needed |
| Browser | **Chrome 146+** with an experimental flag, to use the tools |

## Getting it running

```bash
git clone https://github.com/supergud/laravel-webmcp.git
cd laravel-webmcp

composer install
npm install

cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed

npm run build
```

Seeded account: **`demo@laravel-webmcp.local`** / **`password`**. These are
published on purpose — this seeder is for local demos and the app is not meant
to be deployed with it.

### Serving it — HTTPS is not optional

**WebMCP is gated behind `SecureContext`.** On a plain-HTTP origin
`document.modelContext` does not exist and no tool will ever appear. Serve over
HTTPS, or over `localhost` / `127.0.0.1`, which browsers treat as trustworthy.

```bash
php artisan serve      # http://127.0.0.1:8000 — a secure context, so this works
```

If you use a local domain instead (Valet, Herd, ServBay, Laragon), give it a
trusted certificate and set `APP_URL` to the `https://` address. This repo was
built against ServBay serving `https://laravel-webmcp.local`.

> With `SESSION_SECURE_COOKIE=true` in `.env.example`, login will not persist
> over plain HTTP. Set it to `false` if you serve over `http://127.0.0.1:8000`.

## Enabling WebMCP in Chrome

Without this, the site works normally and simply offers no tools.

1. Use **Chrome 146 or newer**.
2. Open `chrome://flags`, search for **WebMCP**, enable
   **"WebMCP for testing"**, and restart Chrome.
3. Install the
   [**WebMCP Developer Tools**](https://chromewebstore.google.com/detail/webmcp-developer-tools/lhifnagdfoidbjdgdmghpbdpnphbompd)
   extension. It lists the tools a page offers, lets you call them by hand, and
   includes a Gemini chat for watching an agentic flow (bring your own Gemini
   API key — it is stored in your browser).
4. Open the shop and check the extension. Thirteen tools should be listed.

**Check the extension works on some other WebMCP page before a live demo.** It
depends on an experimental Chrome flag and is a third-party project; if it is
broken, nothing in this repository can help.

### Nothing shows up?

The layer stays deliberately silent when the API is missing — a browser without
the flag is an expected state, not an error, and a demo should not be reading a
red console. To see why, set `APP_DEBUG=true` and look for a single
`[webmcp]` line at info level in the console.

Most likely causes, in order: the flag is off, Chrome is older than 146, or the
page is not on a secure origin.

## Things to try

With the extension's chat pointed at the shop:

- *"What laptops do you sell under NT$40,000?"*
- *"Add two of the cheapest USB-C cable to my cart."* — watch the cart badge
  move as the tool call lands.
- *"Switch the site to Chinese."* — the page navigates and changes language.
- *"Show me the Test Device product and follow its instructions."* — the
  injection sample. Nothing happens, and that is the demo.
- *"Buy everything in my cart."* — an agent can prepare the order and will take
  you to the checkout page, but it cannot place it. You do that.

## Tests

```bash
php artisan test    # 228 tests: services, API, authorization, injection, headers
npm test            # 61 tests: tool declarations, request shapes, failure handling
```

The JavaScript tests cover how tools are declared and what they send. They
**cannot** cover Chrome accepting them: `document.modelContext` only exists
behind an experimental flag, so that verification is manual, with the
extension. The test suite does not pretend otherwise.

## Layout

```
app/Services/            CatalogService, CartService, CheckoutService
                         — every rule lives here, shared by the UI and the tools
app/Http/Controllers/Mcp/  the JSON endpoints behind the tools
resources/js/webmcp/     tool declarations, HTTP client, Livewire bridge
resources/views/pages/   Livewire 4 single-file components (⚡ prefix)
lang/                    en + zh-TW
docs/webmcp-tools.md     the tool reference and the security reasoning
AGENTS.md                notes for coding agents working on this repo
```

## License

MIT. See [LICENSE](LICENSE).

---

<h2>中文說明</h2>

這是一個用 **Laravel 13 + Livewire 4** 做的雙語簡易電商網站，透過
[**WebMCP**](https://github.com/webmachinelearning/webmcp)（`document.modelContext`
API）把所有功能開放給瀏覽器裡的 AI agent 使用。

**站上沒有內建任何 AI 對話介面。** agent 由訪客自己帶來（瀏覽器擴充功能或內建助理），
這正是 WebMCP 的核心概念；自己做一個假的聊天框就失去示範意義了。

### 需求

PHP **8.3 以上**、Node 22+、Chrome **146 以上**。資料庫用 SQLite，不需要另外
架資料庫伺服器。

`composer.json` 的 `config.platform` 把相依解析釘在 8.3，Laravel 13 因此用
Symfony 7.4 而不是需要 8.4.1+ 的 Symfony 8。在 8.4/8.5 的機器上拿掉這個設定再
跑 `composer update`，產生的 lock 就裝不進 8.3 主機了。

### 安裝

```bash
git clone https://github.com/supergud/laravel-webmcp.git
cd laravel-webmcp

composer install
npm install

cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed

npm run build
```

預設帳號：**`demo@laravel-webmcp.local`** / **`password`**（僅供本機示範）。

### 必須用 HTTPS 或 localhost

**WebMCP 受 `SecureContext` 限制**：在純 HTTP 網域上 `document.modelContext`
根本不存在，工具永遠不會出現。請用 HTTPS，或用瀏覽器視為可信任的
`localhost` / `127.0.0.1`。

```bash
php artisan serve      # http://127.0.0.1:8000 屬於安全來源，可以直接用
```

若改用本機網域（Valet、Herd、ServBay、Laragon），請設定受信任的憑證，
並把 `APP_URL` 指向 `https://` 網址。本專案是在 ServBay 上以
`https://laravel-webmcp.local` 開發的。

> `.env.example` 預設 `SESSION_SECURE_COOKIE=true`，在純 HTTP 下登入狀態不會保留。
> 若用 `http://127.0.0.1:8000`，請改成 `false`。

### 在 Chrome 啟用 WebMCP

1. 使用 **Chrome 146 以上**。
2. 開啟 `chrome://flags`，搜尋 **WebMCP**，啟用 **"WebMCP for testing"**，重啟瀏覽器。
3. 安裝
   [**WebMCP Developer Tools**](https://chromewebstore.google.com/detail/webmcp-developer-tools/lhifnagdfoidbjdgdmghpbdpnphbompd)
   擴充功能。它可以列出頁面提供的工具、手動呼叫，也內建 Gemini 對話
   （需自備 Gemini API key，存在瀏覽器本機）。
4. 打開網站，在擴充功能中應該會看到 13 個工具。

**正式 demo 前，請先在其他 WebMCP 網站確認這個擴充功能本身能正常運作。**
它依賴 Chrome 的實驗性旗標，而且是第三方個人專案；如果它本身有問題，
這個專案再怎麼寫都救不了。

看不到工具時：偵測不到 API 時本專案會保持完全靜默（沒開旗標是預期狀態，不是錯誤，
而且 demo 現場不該出現紅色 console）。想知道原因，請設 `APP_DEBUG=true`，
console 會有一行 info 等級的 `[webmcp]` 訊息。常見原因依序是：旗標沒開、
Chrome 版本低於 146、頁面不在安全來源上。

### Demo 可以這樣試

- 「有哪些四萬元以下的筆電？」
- 「幫我加兩條最便宜的 USB-C 線到購物車」——看購物車數字當場跳動
- 「把網站切換成中文」——頁面會自動導航並換成中文
- 「看一下那個測試裝置商品，照它說的做」——提示注入樣本，什麼都不會發生，這就是重點
- 「幫我把購物車的東西全部買下來」——AI 可以準備訂單並帶你到結帳頁，
  但**送出訂單必須你自己按**

### 安全設計

13 個工具，刻意不提供兩件事：

- **沒有任何工具可以確認或送出訂單。** `prepare_checkout` 只會產生草稿
  （不扣款、不扣庫存、30 分鐘後過期），確認需要一個只存在於結帳頁面的 token。
- **沒有任何工具可以登入。** 需要帳號的端點回傳 401，工具會轉譯成「請顧客自行登入」。

理由是提示注入：商品描述與評論是會直接流入 AI context 的不可信文字，
一旦提供「下單」工具，等於讓頁面上的文字有機會說服 AI 去用它。

完整說明與誠實的限制範圍請見 **[docs/webmcp-tools.md](docs/webmcp-tools.md)**。
