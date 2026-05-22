# torgodly/messenger-bot

Facebook Messenger webhooks for Laravel (messages, postbacks, quick replies, feed comments). BotMan-style API (`hears`, `payload`, `onComment`) without BotMan.

**PHP 8.3+ · Laravel 12 or 13 · Composer `^2.1`**

---

## Quick start

### Single Facebook Page (default)

```bash
composer require torgodly/messenger-bot:^2.1
php artisan vendor:publish --tag=messenger-bot-config
php artisan messenger-bot:install
```

Register handlers in `AppServiceProvider::boot()`. No tenant config.

### Multi-tenant (many Pages)

```bash
php artisan messenger-bot:install --tenant --model=App\Models\YourFacebookPage
```

Set in `.env` (no quotes around the FQCN):

```env
MESSENGER_BOT_TENANCY_ENABLED=true
MESSENGER_BOT_TENANCY_CONNECTION_MODEL=App\Models\YourFacebookPage
MESSENGER_BOT_TENANCY_PAGE_ID_COLUMN=page_id
MESSENGER_BOT_AUTO_SUBSCRIBE_AFTER_OAUTH=true
MESSENGER_BOT_AUTO_SYNC_MENU_AFTER_OAUTH=true
```

Your model must extend Eloquent `Model` and implement `MessengerBot\Contracts\MessengerConnectable` (trait `InteractsWithMessengerConnection` is recommended). Wrong model class **fails fast in `local` / `testing`**.

---

## Meta Developer checklist

| Step | What to do |
|------|------------|
| App | Messenger product + Facebook Login; add your Page. |
| OAuth redirect | Exact match: `https://YOUR-DOMAIN/messenger-bot/oauth/facebook/callback` (or `MESSENGER_BOT_OAUTH_REDIRECT_URI`). Production needs **HTTPS**; `APP_URL` must match. |
| Webhook | Callback `https://YOUR-DOMAIN/webhook/messenger`, verify token = `MESSENGER_BOT_VERIFY_TOKEN`. |
| Fields | Enable fields in config `webhook_fields` — include **`feed`** for Page comments. `messenger-bot:install` / `sync-page` can subscribe via Graph. |
| Tokens | Production: `MESSENGER_BOT_PAGE_TOKEN_CACHE_STORE` and/or `MESSENGER_BOT_CONNECTION_TOKEN_CACHE_STORE` = `redis` or `database`. |

Routes register **outside** the `web` middleware group (avoids CSRF **419** on Meta POSTs).

---

## Handlers

```php
use MessengerBot\Facades\MessengerBot;

MessengerBot::hears('hi', fn ($bot, $message) => $bot->reply('Hello!'));
MessengerBot::payload('GET_STARTED', fn ($bot, $postback) => $bot->reply('Welcome!'));

MessengerBot::onComment(function ($bot, $comment) {
    $bot->replyToComment($comment->id, 'Thanks!');
});

MessengerBot::fallback(fn ($bot, $message) => $bot->reply('Use the menu.'));
```

- **Priority:** `MessengerBot::hears('pattern', $handler, priority: 10)`
- **Get Started:** `payload()` for `MESSENGER_BOT_GET_STARTED_PAYLOAD` or `MESSENGER_BOT_GET_STARTED_REPLY`
- **Comments:** `CommentCreated` is dispatched before `onComment` handlers (see Events)

Optional host config `comment_handlers.queue` — infrastructure only; the package does **not** ship DB comment rules. See [README-EXTERNAL-RULES.md](README-EXTERNAL-RULES.md).

---

## Multi-tenant

| Config / env | Purpose |
|--------------|---------|
| `tenancy.connection_model` / `MESSENGER_BOT_TENANCY_CONNECTION_MODEL` | Eloquent class implementing `MessengerConnectable` |
| `tenancy.connection_page_id_column` / `MESSENGER_BOT_TENANCY_PAGE_ID_COLUMN` | Column matched to Meta Page ID (default `facebook_page_id`; many apps use `page_id`) |
| Model `messengerFacebookPageIdColumn()` | Override column name on the model |

**OAuth:**

```php
use MessengerBot\Facades\MessengerOAuth;

return MessengerOAuth::redirectToFacebook($pageModel);
```

**Webhook context:**

```php
app(\MessengerBot\Laravel\MessengerCurrentConnection::class)->resolution();
```

**Resolver behaviour:** looks up the model by Page ID column; if missing, falls back to the **connection token page index** and fires `ConnectablePageIdSynced` so your app can persist `page_id` on the row.

**Advanced:** `MESSENGER_BOT_TENANCY_RESOLVER` for a custom `TenantResolver`; optional `EloquentMessengerTenantResolver` subclass (see `stubs/eloquent_messenger_tenant_resolver.php.stub`).

---

## After OAuth automation

When multi-tenant OAuth stores a token, `ConnectionTokenStored` runs. Enable automatic webhook subscribe + persistent menu sync:

```env
MESSENGER_BOT_AUTO_SUBSCRIBE_AFTER_OAUTH=true
MESSENGER_BOT_AUTO_SYNC_MENU_AFTER_OAUTH=true
MESSENGER_BOT_LINK_SKIP_TOKEN_CHECK=false
MESSENGER_BOT_AFTER_OAUTH_QUEUE=false
```

Config block: `messenger-bot.after_connection_token_stored`. Replaces most host wrappers around `ConnectionTokenRepository::put()`.

On failure, the package logs and may queue `SyncPageProfileAfterOAuthJob` when `queue_retry_on_failure` is true.

---

## Events

| Event | When |
|-------|------|
| `WebhookReceived` | Raw webhook payload |
| `MessageReceived` | Incoming message |
| `PostbackReceived` | Postback |
| `CommentCreated` | Top-level feed comment |
| `ConnectionTokenStored` | After `ConnectionTokenRepository::put()` |
| `ConnectablePageIdSynced` | Tenant resolved from token index (DB `page_id` empty) |
| `PostsSynced`, `PostsCacheHit`, `PostsCacheMiss` | Posts sync |
| Outgoing `OutgoingMessage*` | Send pipeline |

---

## DB-driven rules (your app)

The package does not store tenant rules. Load rules from **your** database inside handlers or a small engine — no `optimize:clear` needed.

**Guide:** [README-EXTERNAL-RULES.md](README-EXTERNAL-RULES.md)

### Reference implementation: Matager

Matager (not included in this package) shows a production pattern:

- `CommentRuleEngine` — reads `MetaCommentRule` rows per tenant
- `MetaCommentPostId` — normalizes Graph post IDs for `onlyForPostIds`
- `MessengerBot::onComment` → engine or queued `ProcessMetaCommentRuleJob`
- `PersistFacebookPageAfterOAuthListener` on `ConnectionTokenStored` — saves `page_id` on `FacebookPage`
- Filament admin for rules — stays in the host app

Use Matager as a reference; copy patterns, not the package.

---

## Page posts, Artisan, troubleshooting

**Posts:** `SyncsFacebookPagePosts`, `PostsSyncRequest::forConnectable()`, `RefreshPostsCacheJob::forConnectable()` — see previous examples in [CHANGELOG](CHANGELOG.md) / v2.0 notes.

**Artisan:** `messenger-bot:install`, `install --tenant --model=`, `sync-page`, `token-status`, `clear-page-token`.

**Troubleshooting**

| Symptom | Check |
|---------|--------|
| Comments ignored / `tenant_unresolved` | `feed` subscribed; `MESSENGER_BOT_TENANCY_CONNECTION_MODEL` + `MESSENGER_BOT_TENANCY_PAGE_ID_COLUMN`; Page linked via OAuth; token cache has page index |
| Webhook 403 | `MESSENGER_BOT_APP_SECRET` matches Meta |
| OAuth / webhook 419 | Routes must not use `web` CSRF; package registers outside `web` by default |
| Invalid tenancy model | Exception in `local`/`testing`; production logs critical and disables configurable resolver |

---

## Upgrade `2.0` → `2.1`

1. `composer require torgodly/messenger-bot:^2.1`
2. Publish / merge config for `after_connection_token_stored` and `comment_handlers`
3. Replace host `put()` wrappers with `MESSENGER_BOT_AUTO_SUBSCRIBE_AFTER_OAUTH` / `MESSENGER_BOT_AUTO_SYNC_MENU_AFTER_OAUTH`
4. Listen to `ConnectionTokenStored` for DB hydration (`page_id`) instead of wrapping the repository
5. Remove custom resolvers that only duplicated token page-index lookup — use package fallback + `ConnectablePageIdSynced`

---

## References

- [Messenger templates](https://developers.facebook.com/docs/messenger-platform/send-messages/templates)
- [README-EXTERNAL-RULES.md](README-EXTERNAL-RULES.md)
- [CHANGELOG.md](CHANGELOG.md)
