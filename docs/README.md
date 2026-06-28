# Argus API Documentation

JSON HTTP API over the [`codysseydev/argus`](../../argus) queue-observability core.

## Docs

- [API Reference](api-reference.md) — every endpoint, request/response shapes, status codes
- [Configuration](configuration.md) — config keys, route mounting, pagination defaults
- [Authentication & Authorization](authentication.md) — the auth guard seam, the four gates, 401 vs 403

## Quick start

```bash
composer require codysseydev/argus-api
php artisan vendor:publish --tag=argus-api-config
```

Routes mount under `/argus-api` by default and require a valid Sanctum session.
See [configuration.md](configuration.md) to change the prefix, guard, or middleware.
See [authentication.md](authentication.md) to tighten the authorization gates.
