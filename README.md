# torgodly/messenger-bot (Laravel)

Messenger **Page** webhooks (messages, postbacks) + **Page post comments**, with a small BotMan-style API (`hears`, `payload`, `onComment`) and Laravel events. **No BotMan.**

- PHP **8.3+**, Laravel **12 / 13**
- Namespace: `MessengerBot\`

---

## Installing from Packagist

```bash
composer require torgodly/messenger-bot
php artisan vendor:publish --tag=messenger-bot-config
```

Configure `.env` using the keys in the published [`config/messenger-bot.php`](config/messenger-bot.php). See **Setup** below for Meta webhook, OAuth, and handler registration. Changes are summarized in [`CHANGELOG.md`](CHANGELOG.md).

---

## Developing the package (standalone tests)

From `packages/messenger-bot` after `composer install`:

```bash
composer validate --strict
composer test
vendor/bin/pint --test
```

The host Laravel app in this repo also runs a larger integration suite (`php artisan test` from the project root).

---

## Setup

**Composer** (path package — contributors / monorepo):

```json
{
  "repositories": [{ "type": "path", "url": "packages/messenger-bot", "options": { "symlink": true } }],
  "require": { "torgodly/messenger-bot": "@dev" }
}
```

**`.env`** — `APP_URL` = your public site base (same URL you use in the browser), no trailing slash. Set `MESSENGER_BOT_APP_ID`, `MESSENGER_BOT_APP_SECRET`, `MESSENGER_BOT_VERIFY_TOKEN` (random string; same value in Meta → Webhooks → Verify Token).

**Redirect URI in Meta** — [developers.facebook.com](https://developers.facebook.com/) → your app → **Facebook Login** → **Settings** → **Valid OAuth Redirect URIs**. Paste **exactly** (change only the host):

```text
https://YOUR-PUBLIC-HOST/messenger-bot/oauth/facebook/callback
```

Use the same string as `APP_URL` + this path. If you set `MESSENGER_BOT_OAUTH_REDIRECT_URI` in `.env`, paste **that** full URL into Meta instead. If you change `MESSENGER_BOT_OAUTH_PATH_PREFIX`, replace `messenger-bot/oauth` in the path.

**Log in** — Open `https://YOUR-PUBLIC-HOST/messenger-bot/oauth/facebook` in a browser (Page admin). Token is stored in **cache**. Keep `MESSENGER_BOT_PAGE_ACCESS_TOKEN` **empty** for OAuth; if `.env` has any token there, it is used and OAuth steps are skipped. Several Pages: set `MESSENGER_BOT_OAUTH_PREFERRED_PAGE_ID`. Production: `MESSENGER_BOT_PAGE_TOKEN_CACHE_STORE=redis` or `database`.

**Webhook** — Meta → Webhooks → Page: callback `https://YOUR-PUBLIC-HOST/webhook/messenger` (or `APP_URL` + `MESSENGER_BOT_WEBHOOK_PATH`). Verify token = `MESSENGER_BOT_VERIFY_TOKEN`.

**Commands** — `php artisan messenger-bot:install`, then `php artisan messenger-bot:sync-page` when needed (both validate the Page token with Graph `GET /me` before subscribe/menu; use `--skip-token-check` only if you must). `php artisan messenger-bot:token-status` for expiry. `php artisan messenger-bot:clear-page-token` clears the cached OAuth token and Graph singletons so you can reconnect.

**Problems** — Expired token: clear `MESSENGER_BOT_PAGE_ACCESS_TOKEN`, `php artisan cache:clear`, open OAuth URL again. Redirect error: Meta URI must match the callback **exactly**. App secret warning: set `MESSENGER_BOT_APP_SECRET`. [Permissions](https://developers.facebook.com/docs/permissions/reference). All env keys: [`config/messenger-bot.php`](config/messenger-bot.php).

**Handlers:**

```php
use MessengerBot\Facades\MessengerBot;

MessengerBot::hears('hi', fn ($bot) => $bot->reply('Hello!'));
```

**Get Started** — If you use `persistent_menu`, Meta requires the same `messenger_profile` call to include **Get Started** (default payload `GET_STARTED`). Handle it with `MessengerBot::payload('GET_STARTED', fn ($b) => $b->reply('Welcome!'));` or change `MESSENGER_BOT_GET_STARTED_PAYLOAD` in `.env` to match. If you omit a payload handler, set `MESSENGER_BOT_GET_STARTED_REPLY` (config `get_started.default_reply`) so the webhook still sends a one-line text reply instead of silence.

**OAuth routes** use configurable Laravel `throttle` limits (`MESSENGER_BOT_OAUTH_THROTTLE_REDIRECT`, `MESSENGER_BOT_OAUTH_THROTTLE_CALLBACK` in `.env`). On callback failure the browser sees a generic message; details are only in the application log.

Webhook + OAuth routes register **outside** `routes/web.php` (avoids CSRF **419** for Meta). `php artisan route:clear` after upgrades if you use `route:cache`.

---

## Graph API version

Default `MESSENGER_BOT_GRAPH_VERSION=v24.0` → `https://graph.facebook.com/v24.0/…`. [Changelog](https://developers.facebook.com/docs/graph-api/changelog/).

---

## Usage

Register handlers in `routes/web.php` or a provider `boot` (see **Setup** for webhook / OAuth URLs).

### Text messages (`hears`)

Plain text the user types in Messenger:

```php
MessengerBot::hears('hi', fn ($bot) => $bot->reply('Hello!'));
```

### Payloads (`payload`) — postback buttons & quick replies

`MessengerBot::payload('SHOW_PRODUCTS', …)` runs when Meta sends a **`postback`** with that `payload` string, or a **`quick_reply`** whose `payload` matches. Users do **not** type `SHOW_PRODUCTS` as text for that handler (unless you also add `hears('SHOW_PRODUCTS', …)`).

**Typical flow**

1. User sends **help** (or anything you match with `hears`).
2. Your bot answers with **buttons** (`type` => `postback`) and/or **quick replies** — each has its own `payload` string.
3. User **taps** a button or quick reply → Meta sends a webhook → your `payload('…')` handler runs.

**Example: “Help” menu with a postback that opens `SHOW_PRODUCTS`**

```php
MessengerBot::hears('help', function ($bot) {
    $bot->buttonTemplate('What do you want to do?', [
        [
            'type' => 'postback',
            'title' => 'Show products',
            'payload' => 'SHOW_PRODUCTS', // must match MessengerBot::payload() below
        ],
        [
            'type' => 'postback',
            'title' => 'Talk to human',
            'payload' => 'HUMAN',
        ],
    ]);
});

// Runs when the user taps “Show products” (postback payload SHOW_PRODUCTS)
MessengerBot::payload('SHOW_PRODUCTS', function ($bot, $postback) {
    $bot->replyGenericTemplate([
        [
            'title' => 'Product 1',
            'subtitle' => 'Cool gadget',
            'image_url' => 'https://example.com/p1.jpg',
            'buttons' => [
                ['type' => 'web_url', 'url' => 'https://example.com', 'title' => 'Buy'],
                ['type' => 'postback', 'title' => 'Details', 'payload' => 'DETAILS_1'],
            ],
        ],
    ]);
});

MessengerBot::payload('HUMAN', fn ($bot) => $bot->reply('An agent will join you shortly.'));
```

### More Send API templates (Meta)

Besides **generic** (`replyGenericTemplate`) and **button** (`buttonTemplate`), the bot exposes builders that match Meta’s structured templates:

| Template | Bot method | Notes |
|----------|------------|--------|
| **Media** | `mediaTemplate('image'\|'video', url: …)` or `attachmentId: …` | Facebook-hosted `url` *or* upload API `attachment_id`; optional buttons. [Media template](https://developers.facebook.com/docs/messenger-platform/send-messages/template/media) |
| **Receipt** | `receiptTemplate([…])` | Order confirmation fields (`recipient_name`, `order_number`, `currency`, `elements`, `summary`, …). [Receipt template](https://developers.facebook.com/docs/messenger-platform/send-messages/template/receipt) |
| **Product** | `productTemplate(['SKU1', ['id' => 'SKU2'], …])` | Page catalog product ids (carousel up to 10). [Product template](https://developers.facebook.com/docs/messenger-platform/send-messages/template/product) |
| **List** | `listTemplate($elements, 'compact'\|'large', $globalButtons)` | 2–4 rows; `large` expects an `image_url` on the first row. [List template](https://developers.facebook.com/docs/messenger-platform/send-messages/template/list) |

**Typed DTOs (recommended)** — same Send API payloads, but constructors document required fields and your IDE can autocomplete. Use `receiptFrom`, `mediaFrom`, `productFrom`, `listFrom` on `$bot`. Classes live under `MessengerBot\Templates\Typed\`. Raw `*Template([...])` methods stay available for Meta fields we have not modeled.

Examples:

```php
// Media (Facebook URL or attachment_id from upload API — not both)
$bot->mediaTemplate('image', url: 'https://www.facebook.com/...');
$bot->mediaTemplate('video', attachmentId: $uploadedId, buttons: [
    ['type' => 'web_url', 'url' => 'https://example.com', 'title' => 'Site'],
]);

// Receipt
$bot->receiptTemplate([
    'recipient_name' => 'Alex',
    'order_number' => '1001',
    'currency' => 'USD',
    'payment_method' => 'Visa',
    'timestamp' => '1428444852',
    'summary' => ['total_cost' => 29.99],
    'elements' => [
        ['title' => 'Item', 'quantity' => 1, 'price' => 29.99, 'currency' => 'USD'],
    ],
]);

// Catalog products
$bot->productTemplate(['retailer_id_1', 'retailer_id_2']);

// List (2–4 elements)
$bot->listTemplate([
    ['title' => 'Row 1', 'subtitle' => 'Details', 'image_url' => 'https://example.com/a.jpg'],
    ['title' => 'Row 2', 'subtitle' => 'More'],
], 'large', [
    ['type' => 'postback', 'title' => 'View all', 'payload' => 'VIEW_ALL'],
]);
```

Typed equivalents (imports at top of your route / handler file):

```php
use MessengerBot\Templates\Typed\ListRow;
use MessengerBot\Templates\Typed\ListTemplateData;
use MessengerBot\Templates\Typed\ListTopElementStyle;
use MessengerBot\Templates\Typed\MediaTemplateData;
use MessengerBot\Templates\Typed\ProductTemplateData;
use MessengerBot\Templates\Typed\ReceiptAddress;
use MessengerBot\Templates\Typed\ReceiptLineItem;
use MessengerBot\Templates\Typed\ReceiptSummary;
use MessengerBot\Templates\Typed\ReceiptTemplateData;

$bot->mediaFrom(MediaTemplateData::fromFacebookUrl('image', 'https://www.facebook.com/...'));
$bot->mediaFrom(MediaTemplateData::fromAttachmentId('video', $attachmentId, buttons: [
    ['type' => 'web_url', 'url' => 'https://example.com', 'title' => 'Site'],
]));

$bot->receiptFrom(new ReceiptTemplateData(
    recipientName: 'Alex',
    orderNumber: '1001',
    currency: 'USD',
    paymentMethod: 'Visa',
    timestamp: '1428444852',
    summary: new ReceiptSummary(totalCost: 29.99, subtotal: 29.99),
    lineItems: [
        new ReceiptLineItem(title: 'Item', price: 29.99, currency: 'USD', quantity: 1),
    ],
    shippingAddress: new ReceiptAddress(
        street1: '1 Hacker Way',
        city: 'Menlo Park',
        postalCode: '94025',
        state: 'CA',
        country: 'US',
    ),
));

$bot->productFrom(new ProductTemplateData(['retailer_id_1', 'retailer_id_2']));

$bot->listFrom(new ListTemplateData(
    rows: [
        new ListRow(title: 'Row 1', subtitle: 'Details', imageUrl: 'https://example.com/a.jpg'),
        new ListRow(title: 'Row 2', subtitle: 'More'),
    ],
    topElementStyle: ListTopElementStyle::Large,
    globalButtons: [
        ['type' => 'postback', 'title' => 'View all', 'payload' => 'VIEW_ALL'],
    ],
));
```

`ReceiptTemplateData` also accepts optional `orderUrl`, `adjustments` (`ReceiptAdjustment` objects), and `extra` (array merged into the Meta payload for uncommon keys).

Airline and other legacy templates are still valid JSON on the Send API; use `replyAttachment([...])` with a hand-built `attachment` if you need a type we have not wrapped yet.

**Quick replies** (same `payload()` registry — user taps a chip under the message):

```php
MessengerBot::hears('menu', function ($bot) {
    $bot->quickReplies('Pick one:', [
        [
            'content_type' => 'text',
            'title' => 'Products',
            'payload' => 'SHOW_PRODUCTS',
        ],
        [
            'content_type' => 'text',
            'title' => 'Support',
            'payload' => 'HUMAN',
        ],
    ]);
});
```

Meta reference: [Send API — buttons & postback](https://developers.facebook.com/docs/messenger-platform/reference/send-api/), [quick replies](https://developers.facebook.com/docs/messenger-platform/send-messages/quick-replies).

### Page comments (`onComment`)

- **All posts:** omit the second argument — runs for every new comment (`verb=add`) on the Page your webhook is tied to.

```php
MessengerBot::onComment(function ($bot, $comment) {
    $bot->replyToComment($comment->id, 'Thanks!');
});
```

- **One specific post** (Graph **`post_id`** from the feed webhook, e.g. `p_1234567890`):

```php
MessengerBot::onComment(function ($bot, $comment) {
    $bot->replyToComment($comment->id, 'Promo question — use code SAVE10.');
}, 'p_1234567890');
```

- **Several posts:**

```php
MessengerBot::onComment(fn ($bot, $c) => $bot->replyToComment($c->id, 'Thanks!'), ['p_111', 'p_222']);
```

`$comment->postId` is set from the webhook when Meta sends it. If Meta omits `post_id`, **post-scoped** handlers do not run; your **general** `onComment` handlers (no second arg) still run.

**Routing:** first match wins; optional `priority` on `hears` / `payload`. Regex hears: `'/^help/i'`.

**IDs:** `replyToComment` / `privateReplyToComment` use Graph comment APIs. `sendMessageToPsid` uses Messenger **PSID** — not always the same as comment `from.id` from Graph.

---

## Events

`WebhookReceived`, `MessageReceived`, `PostbackReceived`, `CommentCreated`, `OutgoingMessageSending`, `OutgoingMessageSent`, `OutgoingMessageFailed`.

---

## Security

Do not log **Page** tokens. Keep webhook **signature** enabled in production.

### Meta POST returns HTTP 403 (Forbidden)

Almost always **`X-Hub-Signature-256`** verification: `MESSENGER_BOT_APP_SECRET` must match **Meta → App settings → Basic → App secret** (no extra spaces). If it is wrong or the raw POST body is altered by a proxy, the check fails.

- **Local / tunnel:** you can set `MESSENGER_BOT_SIGNATURE_ENABLED=false` while testing, then turn it **on** in production with a correct secret.
- If **`MESSENGER_BOT_APP_SECRET` is empty** while signatures are enabled, the middleware **skips** the check and logs a **warning** once (so Meta can still deliver in dev — not safe for production).

You do **not** need `php artisan install:api` or `routes/api.php` for this: the package already registers the webhook **outside** the `web` group. Using the default `api` prefix would change your URL to `/api/webhook/...` unless you reconfigure prefixes.

### Meta POST returns HTTP 419 (“Page expired”)

Laravel’s **`routes/web.php`** file is loaded inside the **`web`** middleware group, which includes **CSRF** checks. Any `MessengerBot::routes()` call placed there still runs **inside** that group, so Facebook’s POSTs fail with **419**.

**Fix:** leave **`MESSENGER_BOT_AUTO_REGISTER_ROUTES=true`** (default) so the package registers the webhook in `MessengerBotServiceProvider` **outside** `web.php`. Do not call `MessengerBot::routes()` from `routes/web.php`. This app’s `bootstrap/app.php` also excludes your webhook path from CSRF as a safety net if a route is still registered under `web`.
