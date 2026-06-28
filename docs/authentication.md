# Authentication and Authorization

## The split

**Authentication** (who the user is) is the consuming application's responsibility.
The package registers its routes behind a middleware stack and never issues tokens,
manages sessions, or touches login flows.

**Authorization** (what an authenticated user may do) is this package's responsibility,
via four Laravel gates.

## Authentication

Routes are wrapped in the middleware derived from `config('argus-api.guard')` and
`config('argus-api.middleware')`. The default derived middleware is `auth:sanctum`,
which means Sanctum must be installed if you leave the default in place. Unauthenticated
requests are rejected by the auth middleware before any controller runs; the `401`
response shape is whatever the app's auth middleware produces.

To use a different guard:

```php
// config/argus-api.php
'guard' => 'api',          // produces auth:api
```

To disable guard derivation entirely and control the full stack:

```php
'guard' => null,
'middleware' => ['auth:custom', 'throttle:60,1'],
```

### ActingUser resolution

Controllers resolve the authenticated user through `ActingUser`, which iterates
the configured guard(s) explicitly:

```php
foreach ((array) config('argus-api.guard', 'sanctum') as $guard) {
    if ($user = $this->auth->guard(trim($guard))->user()) {
        return $user;
    }
}
return $this->auth->guard()->user(); // fallback to default guard
```

This avoids depending on `$request->user()`, which requires the default guard to
have been set as a side effect of middleware ordering.

## Authorization

### Gates

Four gates guard the endpoints:

| Gate | Constant | Endpoints |
|------|----------|-----------|
| `view-jobs` | `Abilities::VIEW_JOBS` | `POST /search`, `GET /jobs/{jobUuid}/history`, `GET /saved-searches/{id}/results` |
| `view-failures` | `Abilities::VIEW_FAILURES` | `POST /failures` |
| `manage-saved-searches` | `Abilities::MANAGE_SAVED_SEARCHES` | All saved-search CRUD endpoints |
| `manage-alerts` | `Abilities::MANAGE_ALERTS` | All alert-rule endpoints |

### Default behavior

`AuthorizationServiceProvider` registers a default closure for each gate that has
not already been defined by the application (`Gate::has` check):

```php
fn (?Authenticatable $user) => (bool) config('argus-api.authorization.allow_by_default', true)
```

With the default config (`allow_by_default: true`), any authenticated user passes
all four gates. Set `allow_by_default` to `false` to deny all gates by default.

Application-defined gates always take precedence: if your app defines a gate before
`AuthorizationServiceProvider` boots, the package's default for that gate is skipped.

### 401 vs 403

- **401**: the app's auth middleware rejects an unauthenticated request before any
  controller runs. The response shape is the app's.
- **403**: `Authorize::check` calls `Gate::forUser($user)->denies($ability)` and
  throws `ForbiddenException`, which renders the package envelope:

```json
{
  "error": {
    "type": "forbidden",
    "message": "You are not authorized to perform [view-jobs].",
    "details": { "ability": "view-jobs" }
  }
}
```

### Customizing gates

**Option 1: flip the default**

Set `allow_by_default` to `false` in `config/argus-api.php`. Every gate denies
until defined explicitly.

**Option 2: publish the authorization provider stub**

```bash
php artisan vendor:publish --tag=argus-api-authorization
```

This copies a stub to `app/Providers/ArgusApiAuthorizationServiceProvider.php`.
Register it in `bootstrap/providers.php`, then edit the gate closures:

```php
$gate->define(Abilities::VIEW_JOBS, fn ($user) => $user->hasRole('ops'));
$gate->define(Abilities::VIEW_FAILURES, fn ($user) => $user->hasRole('ops'));
$gate->define(Abilities::MANAGE_SAVED_SEARCHES, fn ($user) => $user->hasRole('ops'));
$gate->define(Abilities::MANAGE_ALERTS, fn ($user) => $user->hasRole('ops'));
```

Because the stub defines the gates before `AuthorizationServiceProvider` checks
`Gate::has`, the package's permissive defaults are bypassed for every ability you
define.

**Option 3: define gates anywhere in the app**

Register any of the four ability names in any application service provider. The
package skips its default for any ability that already has a definition.

```php
// In AppServiceProvider or any other provider
Gate::define('view-jobs', fn ($user) => $user->can_view_jobs);
```
