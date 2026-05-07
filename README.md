# torgodly/messenger-bot

Facebook Messenger webhooks for Laravel (messages, postbacks, quick replies, feed comments). API similar to BotMan (`hears`, `payload`, `onComment`) without BotMan.

**PHP 8.3+ · Laravel 12 or 13**

## Install

```bash
composer require torgodly/messenger-bot
php artisan vendor:publish --tag=messenger-bot-config
php artisan messenger-bot:install
```

**One Facebook Page (default):** the command above writes the usual `.env` keys, validates the token when possible, and subscribes webhooks. You do **not** configure tenant resolution.

**Several Pages (multi-tenant):** use the same install command with flags so `.env` is filled for you—no custom resolver class is required:

```bash
php artisan messenger-bot:install --tenant --model=App\Models\MessengerConnection
```

(`--model=` is optional in interactive mode; you can add `MESSENGER_BOT_TENANCY_CONNECTION_MODEL` later.) The package resolves incoming webhooks by loading that Eloquent model where `facebook_page_id` matches the Page ID (override column with `MESSENGER_BOT_TENANCY_PAGE_ID_COLUMN`). Set `MESSENGER_BOT_TENANCY_RESOLVER` only if you need a fully custom lookup.

## Configure

| Env / setting | Purpose |
|---------------|---------|
| `APP_URL` | Public base URL (no trailing slash). |
| `MESSENGER_BOT_APP_ID`, `MESSENGER_BOT_APP_SECRET` | Meta app. |
| `MESSENGER_BOT_VERIFY_TOKEN` | Same string in Meta → Webhooks → Verify Token. |
| `MESSENGER_BOT_PAGE_ACCESS_TOKEN` | Optional; leave empty and use OAuth for production. |
| `MESSENGER_BOT_TENANCY_ENABLED`, `MESSENGER_BOT_TENANCY_CONNECTION_MODEL` | Multi-tenant mode and the Eloquent model used for Page → tenant mapping (see install `--tenant`). |

**Meta — Facebook Login:** add redirect URI (exact match):

`https://YOUR-DOMAIN/messenger-bot/oauth/facebook/callback`  
(or `MESSENGER_BOT_OAUTH_REDIRECT_URI` if set)

**Meta — Webhooks (Page):** callback `https://YOUR-DOMAIN/webhook/messenger` (or `MESSENGER_BOT_WEBHOOK_PATH`), same verify token.

Webhook and OAuth routes are registered **outside** the `web` middleware group (no CSRF 419 on Meta POSTs). Set `MESSENGER_BOT_AUTO_REGISTER_ROUTES=false` only if you register the webhook route yourself.

## Usage — handlers

Register once at boot (e.g. `App\Providers\AppServiceProvider::boot()` or `routes/web.php`):

```php
use MessengerBot\Facades\MessengerBot;

MessengerBot::hears('hi', fn ($bot, $message) => $bot->reply('Hello!'));

// Case-insensitive exact match (default). Regex: pattern wrapped in /.../
MessengerBot::hears('/^order\\s+#\\d+$/i', fn ($bot) => $bot->reply('Got your order.'));

MessengerBot::payload('GET_STARTED', fn ($bot, $postback) => $bot->reply('Welcome!'));

MessengerBot::payload('MENU_PRODUCTS', fn ($bot) => $bot->reply('Here are products…'));

// Quick reply payloads use the same payload() registration
MessengerBot::onComment(function ($bot, $comment) {
    $bot->replyToComment($comment->id, 'Thanks!');
});

// Only comments on specific Graph post IDs
MessengerBot::onComment(fn ($bot, $c) => $bot->replyToComment($c->id, 'Hi!'), onlyForPostIds: '123456789');

MessengerBot::fallback(fn ($bot, $message) => $bot->reply('Say hi or use the menu.'));
```

**Priority** (higher runs first): `MessengerBot::hears('pattern', $handler, priority: 10);`

**Get Started:** register `payload()` for `MESSENGER_BOT_GET_STARTED_PAYLOAD` or set `MESSENGER_BOT_GET_STARTED_REPLY` for a default when no handler matches.

## Usage — OAuth (single Page)

Open in browser (as Page admin):

`https://YOUR-DOMAIN/messenger-bot/oauth/facebook`

Token is cached; profile sync runs on install. Clear with `php artisan messenger-bot:clear-page-token`.

## Usage — multi-tenant

1. **Database row per Page** — an Eloquent model implements `MessengerBot\Contracts\MessengerConnectable` and uses `MessengerBot\Laravel\Concerns\InteractsWithMessengerConnection`. By default it reads `tenant_id`, primary key (or `id` column) for the connection id, and `facebook_page_id` for the Graph Page id. Override `messengerTenantIdColumn()`, `messengerConnectionKeyColumn()`, or `messengerFacebookPageIdColumn()` if your columns differ.

2. **Turn it on** — `php artisan messenger-bot:install --tenant --model=Your\\Model` or set `MESSENGER_BOT_TENANCY_ENABLED=true` and `MESSENGER_BOT_TENANCY_CONNECTION_MODEL=Your\\Model`. Webhooks then load that model with `where(facebook_page_id, <Page id from Meta>)`. If no row matches, legacy behaviour depends on `MESSENGER_BOT_TENANCY_FALLBACK_LEGACY` / `MESSENGER_BOT_TENANCY_SKIP_UNRESOLVED` (see config comments).

3. **OAuth** — after the user is logged into your app, send them to Facebook with the connectable model so the callback can store the token for that row:

```php
use MessengerBot\Facades\MessengerOAuth;

return MessengerOAuth::redirectToFacebook($store);
```

4. **Posts and background jobs** — same object everywhere:

```php
use MessengerBot\Kernel\Contracts\SyncsFacebookPagePosts;
use MessengerBot\Kernel\Posts\PostsSyncOptions;
use MessengerBot\Kernel\Posts\PostsSyncRequest;
use MessengerBot\Laravel\Jobs\RefreshPostsCacheJob;

$posts = app(SyncsFacebookPagePosts::class);
$result = $posts->sync(PostsSyncRequest::forConnectable($store), new PostsSyncOptions);

RefreshPostsCacheJob::forConnectable($store, bypassCache: false);
```

5. **Inside webhook handlers** — optional active resolution:

```php
use MessengerBot\Laravel\MessengerCurrentConnection;

$pageContext = app(MessengerCurrentConnection::class)->resolution(); // ?TenantResolution
```

`MESSENGER_BOT_OAUTH_DUAL_WRITE_LEGACY` (default `true`) also fills the legacy global token cache for tools that still expect a single Page. `MESSENGER_BOT_OAUTH_REQUIRE_MT_SIGNATURE=false` is for local debugging only. Swap `ConnectionTokenRepository` in the container if you store tokens in the database instead of cache.

## Usage — multi-tenant (advanced)

- **`MESSENGER_BOT_TENANCY_RESOLVER`** — bind a class implementing `MessengerBot\Kernel\Contracts\TenantResolver` when the default Eloquent lookup is not enough.
- **`MessengerBot\Laravel\Tenancy\EloquentMessengerTenantResolver`** — optional base class if you prefer a dedicated resolver class over config-only `connection_model` (see `stubs/eloquent_messenger_tenant_resolver.php.stub`).
- **Raw OAuth query params** — `tenant_id`, `connection_id`, `mt_sig` where `mt_sig` is `hash_hmac('sha256', $tenantId."\n".$connectionId, config('messenger-bot.app_secret'))`; the package signs these for you via `MessengerOAuth`.

## Usage — Page posts (Graph)

Inject `MessengerBot\Kernel\Contracts\SyncsFacebookPagePosts`.

```php
use MessengerBot\Kernel\Contracts\SyncsFacebookPagePosts;
use MessengerBot\Kernel\Posts\PostsSyncOptions;
use MessengerBot\Kernel\Posts\PostsSyncRequest;
use MessengerBot\Kernel\Tenancy\ConnectionId;
use MessengerBot\Kernel\Tenancy\TenantId;

$posts = app(SyncsFacebookPagePosts::class);

// From a MessengerConnectable model (multi-tenant or single-tenant with opaque keys):
$result = $posts->sync(PostsSyncRequest::forConnectable($store), new PostsSyncOptions(/* … */));

// Or explicit value objects:
$result = $posts->sync(
    new PostsSyncRequest(
        tenantId: new TenantId('tenant-ulid'),
        connectionId: new ConnectionId('connection-ulid'),
        pageId: '1234567890',
        since: new \DateTimeImmutable('2026-05-01T00:00:00Z'),
        until: new \DateTimeImmutable('now'),
        maxPosts: 500,
        fields: ['id', 'message', 'created_time', 'permalink_url'],
    ),
    new PostsSyncOptions(
        limitPerRequest: 25,
        maxApiCalls: 50,
        maxDurationMs: 0,
        bypassCache: false,
        useStampedeLock: true,
        cacheTtlSeconds: 300,
    ),
);
```

**Single-tenant:** tenancy off; `forConnectable()` still works with any object implementing `MessengerConnectable` (stable keys + Page id). Legacy token must match `pageId` when a legacy `page_id` is cached.

**Invalidate post list cache:**

```php
app(SyncsFacebookPagePosts::class)->bumpPostsCacheVersion(new ConnectionId($store->messengerConnectionKey()));
```

**Background refresh (raw keys or connectable):**

```php
use MessengerBot\Laravel\Jobs\RefreshPostsCacheJob;

RefreshPostsCacheJob::forConnectable($store);
RefreshPostsCacheJob::dispatch(tenantId: '…', connectionId: '…', pageId: '…');
```

Example DB migration stub: `stubs/migrations/create_mb_connections_table.php.stub`.

## Artisan

| Command | Purpose |
|---------|---------|
| `messenger-bot:install` | Publish config, `.env` keys, token check, subscribe, menu. |
| `messenger-bot:install --tenant [--model=]` | Same as above plus multi-tenant `.env` defaults (`MESSENGER_BOT_TENANCY_*`). |
| `messenger-bot:sync-page` | Re-subscribe webhook fields, persistent menu. |
| `messenger-bot:token-status` | Cached token / expiry hint. |
| `messenger-bot:clear-page-token` | Clear legacy cached Page token. |

`--skip-token-check` on install/sync skips Graph `GET /me`.

## Events

`WebhookReceived`, `MessageReceived`, `PostbackReceived`, `CommentCreated`, `PostsSynced`, `PostsCacheHit`, `PostsCacheMiss`, plus outgoing send events.

## References

- [Messenger templates](https://developers.facebook.com/docs/messenger-platform/send-messages/templates) (use methods on `Bot`).
- [README-EXTERNAL-RULES.md](README-EXTERNAL-RULES.md) — store tenant rules and menus in **your** app (examples).
- [CHANGELOG.md](CHANGELOG.md)

**403 webhook:** wrong app secret vs Meta, or leave `MESSENGER_BOT_SIGNATURE_ENABLED=false` only in local tests.
