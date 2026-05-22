# Release checklist

1. **CHANGELOG** — Move items from `[Unreleased]` to a dated section; set the version heading (e.g. `## [2.1.0] - YYYY-MM-DD`).
2. **composer.json** — Set `support.source` / `support.issues` and `homepage` to your public Git host (Packagist reads these).
3. **Tests** — From repo root: `php artisan test`. From package: `cd packages/messenger-bot && composer install && composer test`.
4. **Validate** — `composer validate --strict` in `packages/messenger-bot`.
5. **Tag** — `git tag -a v2.1.0 -m "Release v2.1.0"` and push tags.
6. **Packagist** — Submit or update the package; confirm the new tag is picked up.
7. **GitHub** — Create a Release with notes copied from CHANGELOG.

If the library lives only inside a monorepo, use **git subtree split** (or a dedicated mirror repo) so Packagist sees a repository whose **root** is the package contents.
