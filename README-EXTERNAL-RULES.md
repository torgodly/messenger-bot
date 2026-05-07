# External rules and menus (your app, not the package)

The **messenger-bot** package gives you webhooks, tokens, and **registration helpers** (`MessengerBot::hears`, `payload`, `onComment`, `fallback`). It does **not** store per-tenant “rules” in the database—that belongs in **your** Laravel app.

---

## 1. Simplest approach: **no** `php artisan optimize:clear` when rules change

You **do not** need `optimize:clear` (or a deploy) every time someone edits `messenger_rules`.

**Do this instead:**

1. In `AppServiceProvider::boot()` (once), register **only a few fixed handlers**—not one closure per DB row.
2. Inside those handlers, **read `messenger_rules` from the database** (filter by tenant if you use multi-tenant).

Every incoming webhook is a new request: your query runs again, so **the next message already uses the updated rules**. Nothing is “frozen” in PHP unless you use something like **Laravel Octane** (see note below)—and even then you only read fresh data from the DB each time; you still do **not** need `optimize:clear` for rule rows.

**Minimal example — one engine for text messages:**

```php
// app/Providers/AppServiceProvider.php

use App\Messenger\DbRuleEngine;
use MessengerBot\Facades\MessengerBot;

public function boot(): void
{
    MessengerBot::fallback(function ($bot, $message) {
        app(DbRuleEngine::class)->handleIncomingMessage($bot, $message);
    });
}
```

```php
// app/Messenger/DbRuleEngine.php (sketch)

namespace App\Messenger;

use App\Models\MessengerRule;
use MessengerBot\Bot\Bot;
use MessengerBot\Laravel\MessengerCurrentConnection;
use MessengerBot\Messages\IncomingMessage;

final class DbRuleEngine
{
    public function handleIncomingMessage(Bot $bot, IncomingMessage $message): void
    {
        $text = trim((string) ($message->text ?? ''));
        $tenantKey = app(MessengerCurrentConnection::class)->resolution()?->tenantId->value;

        $rules = MessengerRule::query()
            ->when($tenantKey, fn ($q) => $q->where('tenant_key', $tenantKey))
            ->where('enabled', true)
            ->orderByDesc('priority')
            ->get();

        foreach ($rules as $rule) {
            if ($rule->type === 'hears' && $text !== '' && strcasecmp($text, $rule->pattern) === 0) {
                $bot->reply($rule->reply_text);

                return;
            }
        }

        $bot->reply('Say hi or use the menu.');
    }
}
```

**Postbacks:** register one handler per fixed payload your Meta buttons send, **or** one `MessengerBot::fallback`-style path that inspects postbacks if you expose that in your stack—many apps add `MessengerBot::payload('HELP', …)` for known buttons and keep DB-driven text rules in `DbRuleEngine` only for free text.

**Comments:** same idea—`MessengerBot::onComment(fn ($bot, $comment) => app(DbRuleEngine::class)->handleComment($bot, $comment));` and query `messenger_comment_rules` inside `handleComment`.

**Octane / long-lived workers:** still use the pattern above (read DB inside the handler). You only need worker reload if you **registered** rules once at process start from DB into closures and never re-read—avoid that by reading the DB **inside** the handler.

---

## 2. Optional: register one closure per DB row in `boot()`

You *can* loop `MessengerRule::all()` in `boot()` and call `MessengerBot::hears(...)` per row. That works on normal PHP-FPM (each request re-boots). Downsides: many closures, heavier boot, and with **Octane** the list is fixed until the worker restarts—so for editable rules, **prefer section 1**.

```php
use App\Models\MessengerRule;
use MessengerBot\Facades\MessengerBot;

public function boot(): void
{
    foreach (MessengerRule::query()->where('enabled', true)->orderBy('priority')->get() as $rule) {
        if ($rule->type === 'hears') {
            MessengerBot::hears($rule->pattern, function ($bot, $message) use ($rule) {
                // …
            }, priority: (int) $rule->priority);
        }
    }
}
```

---

## 3. Register comment rules from the database

Same as messages: **one** `onComment` handler that loads rows inside the method avoids cache/reload issues.

```php
use MessengerBot\Facades\MessengerBot;

MessengerBot::onComment(function ($bot, $comment) {
    app(\App\Messenger\DbRuleEngine::class)->handleComment($bot, $comment);
});
```

---

## 4. Persistent menu built outside `config/messenger-bot.php`

Meta expects a `persistent_menu` array (see [Messenger persistent menu](https://developers.facebook.com/docs/messenger-platform/send-messages/persistent-menu)). Build it from your DB when an admin saves the menu, then push to Graph:

```php
use MessengerBot\Profile\PersistentMenuConfigurator;

$menu = [
    [
        'locale' => 'default',
        'composer_input_disabled' => false,
        'call_to_actions' => [
            ['type' => 'postback', 'title' => 'Help', 'payload' => 'HELP'],
        ],
    ],
];

app(PersistentMenuConfigurator::class)->sync($menu);
```

- **`locale`:** `'default'` is the fallback block; you can add more entries (e.g. `en_US`) for other languages.
- **`composer_input_disabled`:** `false` = user can still type; `true` = menu-only (no free text).

**Multi-tenant:** call `sync()` when you have the correct Page token / tenant context for that Facebook Page.

---

## 5. Checklist

| Concern | Where it lives |
|--------|----------------|
| Rule / menu **storage** | Your migrations and models |
| Admin UI | Your controllers / Livewire / Filament |
| **Avoid `optimize:clear` for rule edits** | Read rules **inside** handlers (section 1) |
| **Calling** Meta (menu, replies) | `MessengerBot` + `Bot`, `PersistentMenuConfigurator` |
| Webhook **routing** | This package (unchanged) |

**Remember:** `php artisan optimize:clear` clears Laravel’s **config/route/view** caches. It is **not** the tool for “tenant updated a row in `messenger_rules`.” For that, read the table when the message arrives.
