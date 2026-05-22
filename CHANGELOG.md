# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.2.0] - 2026-05-07

### Added

- `ExchangeOAuthCodeForManagedPages`, `CompleteOAuthPageLink`, `PendingOAuthPages` for multi-Page OAuth without auto-picking the first Page.
- `ValidatesMessengerPageLink` contract (`MESSENGER_BOT_VALIDATES_PAGE_LINK`) — host enforces one Page per tenant and one tenant per Page.
- `PageLinkRejectedException` and session flash key `messenger_bot_oauth_error` on rejected links.
- Config: `MESSENGER_BOT_OAUTH_PENDING_PAGES_URL`, pending Pages cache prefix/TTL, preferred Page fast-path unchanged.

### Changed

- **Breaking:** OAuth callback with 2+ managed Pages caches all Pages and redirects to `pending_pages_redirect_url?token=` — no token stored until `CompleteOAuthPageLink::complete()`.
- Multi-tenant boot validation also requires `validates_page_link` and `pending_pages_redirect_url`.

Composer: **`^2.2`**. Git tag: **`v2.2.0`**.

## [2.1.0] - 2026-05-07

### Added

- `ConnectionTokenStored` event dispatched after `ConnectionTokenRepository::put()` (cache implementation).
- `ConnectablePageIdSynced` when tenant resolution uses the connection-token page index (DB row missing Page ID).
- `after_connection_token_stored` config and `SyncPageProfileAfterOAuthListener` (+ `SyncPageProfileAfterOAuthJob`) for post-OAuth webhook subscribe and persistent menu sync.
- Tenant resolver fallback via `getByPageId()` when Eloquent lookup misses.
- `TenancyConfigurationValidator` — invalid `connection_model` throws in `local`/`testing`, logs critical in production.
- Install `--tenant --model=` validates `MessengerConnectable`; expanded Meta checklist.
- `comment_handlers` config (queue hints for host apps; no DB rules in package).

### Changed

- README restructured (quick start, Meta checklist, after-OAuth automation, events, Matager reference, troubleshooting, upgrade guide).
- Tenancy docs: `connection_page_id_column` / `MESSENGER_BOT_TENANCY_PAGE_ID_COLUMN` (e.g. `page_id`).

Composer: **`^2.1`**. Git tag: **`v2.1.0`**.

## [2.0.0] - 2026-05-07

### Added

- Multi-tenant Messenger support: `MessengerConnectable`, `InteractsWithMessengerConnection`, configurable Page-ID resolver (`messenger-bot.tenancy.connection_model`), `MessengerOAuth` / signed OAuth state, connection-scoped tokens and posts cache helpers, `MessengerCurrentConnection`, `ConfigurableMessengerTenantResolver`, and `php artisan messenger-bot:install --tenant [--model=]`.
- `README-EXTERNAL-RULES.md` cookbook for DB-driven rules and menus in the host application.

### Changed

- `ConfigurableMessengerTenantResolver` now extends `EloquentMessengerTenantResolver` (single code path for Eloquent Page-ID lookup).
- `EloquentMessengerTenantResolver` validates model class and column before querying.

Composer constraint: use **`^2.0`** when depending on this line (see `composer.json` `version` and Git tag **`v2.0.0`**).

## [1.0.0] - 2026-05-04

### Added

- Laravel package `torgodly/messenger-bot`: Messenger Page webhooks, postbacks, feed comments, Bot-style API (`hears`, `payload`, `onComment`), Graph client, OAuth flow, install/sync Artisan commands.
- Page access token health check (`GET /me`) before webhook subscribe and persistent menu sync; `--skip-token-check` on install and sync commands.
- Default Get Started text reply when no `MessengerBot::payload` handler is registered (`MESSENGER_BOT_GET_STARTED_REPLY` / `get_started.default_reply`).
- `GraphContainerReset` to clear Graph-related container singletons after OAuth or token changes.
- `messenger-bot:clear-page-token` Artisan command.
- OAuth route throttling and generic callback error responses with full exception logging server-side.

After the Git repository is public, add compare/release URLs at the bottom of this file (Keep a Changelog style).
