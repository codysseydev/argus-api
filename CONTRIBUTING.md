# Contributing

## Requirements

- PHP 8.5+
- [Composer](https://getcomposer.org/)
- A running Redis instance (required because `ArgusServiceProvider` opens a Redis
  buffer connection at boot, even in test mode)

## Setup

```bash
git clone https://github.com/codysseydev/argus-api.git
cd argus-api
composer install
```

The committed `composer.json` depends on `codysseydev/argus` from Packagist. If you
need to develop against an unreleased local checkout of the core, wire a path
repository temporarily:

```bash
# DO NOT commit these changes
composer config repositories.argus path ../argus
composer update codysseydev/argus
```

Revert both changes (`composer.json` and `composer.lock`) before opening a pull
request. The CI workflow handles the path-repo wiring itself; your committed
`composer.json` must always point at Packagist.

## Running tests

Redis must be reachable on `127.0.0.1:6379` (or override `REDIS_HOST`).

```bash
composer test
# or
vendor/bin/phpunit
```

Tests use in-memory fakes for all storage contracts, so no database is required.

## Code style

```bash
vendor/bin/pint
```

Run Pint before pushing. CI enforces `--test` mode and will fail on any diff.

## Pull requests

- One concern per PR.
- Add or update tests for any changed behaviour.
- Update `CHANGELOG.md` under `[Unreleased]`.
- Ensure `composer test` and `vendor/bin/pint --test` both pass locally before
  pushing.

## Code of conduct

Be direct, be kind, and keep feedback about the work.
