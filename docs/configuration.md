# Configuration

## Publishing the config file

```bash
php artisan vendor:publish --tag=argus-api-config
```

This copies `config/argus-api.php` into your application's `config/` directory.
Without publishing, the package merges its defaults automatically; publish only
when you need to override values.

## How routes are mounted

`ArgusApiServiceProvider` registers a single route group in its `boot()` method:

```php
Route::group([
    'prefix' => config('argus-api.prefix', 'argus-api'),
    'middleware' => $this->routeMiddleware(),
], function (): void {
    require __DIR__.'/../routes/argus-api.php';
});
```

`routeMiddleware()` builds the middleware stack by combining
`config('argus-api.middleware')` with the auth middleware derived from
`config('argus-api.guard')`. If `guard` is `null`, only the explicit
`middleware` list is used.

Routes are registered as named routes under the `argus-api.*` namespace (e.g.
`argus-api.search`, `argus-api.jobs.history`).

## Config keys

### `prefix`

The URL prefix all Argus API routes mount under.

**Default:** `argus-api`  
**Env:** none (set directly in the config file)

```php
'prefix' => 'argus-api',
```

### `guard`

The auth guard (or array of guards) the package derives an `auth:` middleware
from and appends to the route group. Accepts a string or an array of guard names.

Set to `null` to disable guard derivation entirely and take full manual control
via the `middleware` key.

**Default:** `sanctum` (via `env('ARGUS_API_GUARD', 'sanctum')`)  
**Env:** `ARGUS_API_GUARD`

```php
'guard' => env('ARGUS_API_GUARD', 'sanctum'),
```

When an array of guards is provided, the auth middleware becomes
`auth:guard1,guard2,...`. The `ActingUser` resolver iterates those same guards in
order to find the authenticated user.

### `middleware`

Supporting middleware that runs before the derived auth middleware: session
handling, CSRF protection, throttling, etc. This is for infrastructure middleware
only; the auth middleware is derived from `guard` and appended automatically.

**Default:** `[]`

```php
'middleware' => [],
```

### `pagination.default_limit`

The page size applied when the request body omits `limit`.

**Default:** `100`

```php
'pagination' => [
    'default_limit' => 100,
    ...
],
```

### `pagination.max_limit`

The upper bound on `limit`. Any client-supplied value above this is silently
clamped to `max_limit`.

**Default:** `500`

```php
'pagination' => [
    ...
    'max_limit' => 500,
],
```

### `authorization.allow_by_default`

The default verdict for every Argus gate that the application has not overridden.

`true` (default): any authenticated user passes all four gates.  
`false`: all gates deny unless the app defines them explicitly.

**Default:** `true`

```php
'authorization' => [
    'allow_by_default' => true,
],
```

## Example: tighten to a specific guard

```php
// config/argus-api.php
return [
    'prefix' => 'api/queue',
    'guard' => 'api',
    'middleware' => ['throttle:60,1'],
    'pagination' => [
        'default_limit' => 50,
        'max_limit' => 200,
    ],
    'authorization' => [
        'allow_by_default' => false,
    ],
];
```

With `allow_by_default` set to `false`, every gate returns `403` until you
define them in your own provider (see [authentication.md](authentication.md)).
