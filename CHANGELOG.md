# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-05-04

### Added

- Laravel package `torgodly/messenger-bot`: Messenger Page webhooks, postbacks, feed comments, Bot-style API (`hears`, `payload`, `onComment`), Graph client, OAuth flow, install/sync Artisan commands.
- Page access token health check (`GET /me`) before webhook subscribe and persistent menu sync; `--skip-token-check` on install and sync commands.
- Default Get Started text reply when no `MessengerBot::payload` handler is registered (`MESSENGER_BOT_GET_STARTED_REPLY` / `get_started.default_reply`).
- `GraphContainerReset` to clear Graph-related container singletons after OAuth or token changes.
- `messenger-bot:clear-page-token` Artisan command.
- OAuth route throttling and generic callback error responses with full exception logging server-side.

After the Git repository is public, add compare/release URLs at the bottom of this file (Keep a Changelog style).
