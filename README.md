# torgodly/messenger-bot

Facebook **Messenger** webhooks for Laravel: messages, postbacks, quick replies, and optional Page **feed** comments. Small API similar to BotMan (`hears`, `payload`, `onComment`) — **not** BotMan.

**Requirements:** PHP 8.3+, Laravel 12 or 13.

---

## Install

```bash
composer require torgodly/messenger-bot
php artisan vendor:publish --tag=messenger-bot-config
```

Then set `.env` (see `config/messenger-bot.php` for every key). Easiest path:

```bash
php artisan messenger-bot:install
```

That publishes config, adds missing `MESSENGER_BOT_*` lines to `.env`, and (when you have a Page token) subscribes webhooks and syncs the menu.

---

## What you configure

| You need | Notes |
|----------|--------|
| `APP_URL` | Public base URL of this app (no trailing slash). |
| `MESSENGER_BOT_APP_ID` / `MESSENGER_BOT_APP_SECRET` | Meta app. |
| `MESSENGER_BOT_VERIFY_TOKEN` | Any random string — same value in Meta → Webhooks → Verify Token. |
| Page token | Leave `MESSENGER_BOT_PAGE_ACCESS_TOKEN` empty and use **OAuth**, or paste a token for local tests. |

**OAuth (recommended):** In Meta → Facebook Login → *Valid OAuth Redirect URIs*, add exactly:

`https://YOUR-DOMAIN/messenger-bot/oauth/facebook/callback`

(Or the full URL if you set `MESSENGER_BOT_OAUTH_REDIRECT_URI`.) Open `https://YOUR-DOMAIN/messenger-bot/oauth/facebook` in a browser while logged in as a Page admin.

**Webhook:** In Meta → Webhooks → Page, callback URL = `https://YOUR-DOMAIN/webhook/messenger` (or your `MESSENGER_BOT_WEBHOOK_PATH`). Same verify token as above.

---

## Useful commands

| Command | Purpose |
|---------|--------|
| `php artisan messenger-bot:install` | Config + `.env` keys + subscribe + menu (needs token). |
| `php artisan messenger-bot:sync-page` | Re-run subscribe/menu after changes. |
| `php artisan messenger-bot:token-status` | Token expiry hint. |
| `php artisan messenger-bot:clear-page-token` | Clear cached OAuth token; connect again. |

Use `--skip-token-check` on install/sync only if you really must skip the Graph `GET /me` check.

---

## Handlers

Register in `routes/web.php` or a service provider `boot()`:

```php
use MessengerBot\Facades\MessengerBot;

MessengerBot::hears('hi', fn ($bot) => $bot->reply('Hello!'));

MessengerBot::payload('GET_STARTED', fn ($bot) => $bot->reply('Welcome!'));

MessengerBot::onComment(function ($bot, $comment) {
    $bot->replyToComment($comment->id, 'Thanks!');
});
```

Postbacks and quick replies use the same `payload('SOME_KEY', …)` if the payload string matches. **Get Started:** if you use a persistent menu, Meta sends a Get Started postback — register `payload` for `MESSENGER_BOT_GET_STARTED_PAYLOAD` or set `MESSENGER_BOT_GET_STARTED_REPLY` for a default text when no handler is registered.

---

## Going deeper

- **Templates** (generic, button, media, receipt, list, product, etc.) and typed helpers live on `Bot` — see [Send API templates](https://developers.facebook.com/docs/messenger-platform/send-messages/templates).
- **Events:** `MessageReceived`, `PostbackReceived`, `CommentCreated`, `WebhookReceived`, plus outgoing message events.
- **Changelog:** [CHANGELOG.md](CHANGELOG.md).

Webhook and OAuth routes are registered **outside** the `web` middleware group so Meta is not blocked by CSRF (no 419). Keep `MESSENGER_BOT_AUTO_REGISTER_ROUTES=true` unless you register routes yourself.

**403 on webhook:** usually bad `MESSENGER_BOT_APP_SECRET` vs Meta. For local tests you can set `MESSENGER_BOT_SIGNATURE_ENABLED=false`; use a real secret in production.

---

## Path repo (contributors)

```json
{
  "repositories": [{ "type": "path", "url": "packages/messenger-bot", "options": { "symlink": true } }],
  "require": { "torgodly/messenger-bot": "@dev" }
}
```
