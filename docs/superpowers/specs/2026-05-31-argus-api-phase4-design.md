# Argus API (Phase 4) Design

A standalone Laravel Composer package, `acme/queue-observability-api`, that exposes
the existing `acme/argus` core package's query and management services over a JSON
HTTP API. This is Phase 4 of five; Phase 5 builds a React client against the
contract defined here.

## Goal and boundary

The package puts an HTTP face on the core's public services and nothing more. It
calls only `JobQueryService`, `SavedSearchService`, and `AlertService`. It contains
no storage knowledge, no backend SQL, and no direct table access. It must keep
working unchanged when the core swaps Postgres for another backend.

Dependency direction (one way only):

```
React SPA (Phase 5) -> ArgusApi controllers -> core services -> core storage
```

ArgusApi imports zero classes from `Argus\Storage\*` and never touches a database
connection. A static test enforces this (see Testing).

### Authentication vs authorization (the crux of this phase)

- **Authentication (who the user is) is NOT this package's job.** The consuming app
  owns it (Laravel Sanctum by default). This package ships no login, token issuance,
  or session handling.
- The package registers its routes behind a **configurable, overridable middleware
  stack**, defaulting to Sanctum's auth middleware. The app plugs in its own guard.
- **Authorization (what an authenticated user may do) IS this package's job**, via
  publishable Laravel Gates. Sensible defaults ship; the app overrides them.
- Net effect: the app answers "is this a valid logged-in user" (rejecting
  unauthenticated requests before our controllers run); this package answers "may
  this user run this query" (403 on an authenticated-but-unauthorized request).

## Package layout

Separate sibling git repo at `/Users/davorminchorov/Code/GitHub/queue-observability-api`.

```
queue-observability-api/
├── composer.json          # "acme/queue-observability-api", requires "acme/argus"
├── phpunit.xml.dist
├── pint.json
├── config/argus-api.php   # prefix, middleware stack, pagination defaults
├── routes/argus-api.php
├── src/
│   ├── ArgusApiServiceProvider.php
│   ├── Authorization/
│   │   └── AuthorizationServiceProvider.php   # publishable gate defaults
│   ├── Http/
│   │   ├── Controllers/    # single-action, final readonly
│   │   ├── Requests/       # FormRequests, array-based rules
│   │   ├── Resources/      # DTO -> array presenters (plain, no Eloquent)
│   │   └── Support/        # FilterInput, ApiResponse envelope helpers
│   └── Exceptions/         # NotFoundException (renders its own 404 envelope)
├── tests/Feature/
└── README.md               # API reference + TypeScript contract block
```

### Composer dependency

During co-development the core resolves via a Composer path repository so it points
at the local checkout, not a published release:

```json
{
    "name": "acme/queue-observability-api",
    "type": "library",
    "require": {
        "php": "^8.5",
        "acme/argus": "*",
        "illuminate/contracts": "^13.0",
        "illuminate/http": "^13.0",
        "illuminate/routing": "^13.0",
        "illuminate/support": "^13.0"
    },
    "require-dev": {
        "laravel/pint": "^1.0",
        "orchestra/testbench": "^11.0",
        "phpunit/phpunit": "^12.0"
    },
    "repositories": [
        { "type": "path", "url": "../queue-observability" }
    ],
    "autoload": { "psr-4": { "ArgusApi\\": "src/" } },
    "autoload-dev": { "psr-4": { "ArgusApi\\Tests\\": "tests/" } },
    "extra": { "laravel": { "providers": ["ArgusApi\\ArgusApiServiceProvider"] } }
}
```

When the package is later published, the path repository is replaced by a real
version constraint from a VCS/Packagist source; no source code changes.

PSR-4 namespace: `ArgusApi\`. A distinct top-level namespace keeps the boundary with
the core's `Argus\` tree unmistakable.

## Endpoints

Default route prefix `argus-api` (configurable). Every route sits behind the
configurable middleware stack and an authorization gate.

| Method | Path | Core call | Gate |
|---|---|---|---|
| POST | `/search` | `JobQueryService::search` + `::count` | `view-jobs` |
| GET | `/jobs/{jobUuid}/history` | `JobQueryService::getHistory` | `view-jobs` |
| POST | `/failures` | `JobQueryService::groupFailures` | `view-failures` |
| GET | `/saved-searches` | `SavedSearchService::all` | `manage-saved-searches` |
| POST | `/saved-searches` | `SavedSearchService::create` | `manage-saved-searches` |
| GET | `/saved-searches/{id}` | `SavedSearchService::find` | `manage-saved-searches` |
| PUT | `/saved-searches/{id}` | `SavedSearchService::update` | `manage-saved-searches` |
| DELETE | `/saved-searches/{id}` | `SavedSearchService::delete` | `manage-saved-searches` |
| GET | `/saved-searches/{id}/results` | `SavedSearchService::results` | `view-jobs` |
| GET | `/saved-searches/{id}/alert-rules` | `AlertService::forSavedSearch` | `manage-alerts` |
| POST | `/saved-searches/{id}/alert-rules` | `AlertService::attach` | `manage-alerts` |
| GET | `/alert-rules` | `AlertService::all` | `manage-alerts` |
| GET | `/alert-rules/{id}` | `AlertService::find` | `manage-alerts` |
| PUT | `/alert-rules/{id}` | `AlertService::update` | `manage-alerts` |
| DELETE | `/alert-rules/{id}` | `AlertService::delete` | `manage-alerts` |

Decisions:

- `GET /saved-searches/{id}/results` is included beyond the bare CRUD list because
  `SavedSearchService::results()` already exists and re-running a saved search is its
  primary purpose; Phase 5 needs it. Guarded by `view-jobs` because it returns job
  data, not saved-search configuration.
- Alert create/list are nested under `/saved-searches/{id}/alert-rules` ("attached to
  saved searches"); get/update/delete are flat `/alert-rules/{id}` because a rule has
  its own identity.

### Authorization gates

Four gates: `view-jobs`, `view-failures`, `manage-saved-searches`, `manage-alerts`.
Saved-search and alert reads are folded into their `manage-*` gate to keep the set
small. Splitting read vs write later is a trivial addition.

## Request and response shapes

### Filter object (reused verbatim from core `FilterCodec`)

This is the only filter representation. `POST /search` and `POST /failures` take it
as the request body; `FilterInput` validates it then calls the core's
`FilterCodec::decode()` to produce a `JobFilter`.

```json
{
  "jobClass": null,
  "queue": "emails",
  "tenantId": "acme",
  "status": "failed",
  "attemptMin": null,
  "attemptMax": null,
  "since": "2026-05-01T00:00:00+00:00",
  "until": null,
  "correlationKey": null,
  "correlationValue": null,
  "limit": 100,
  "offset": 0
}
```

`status` accepts one of `queued|processing|processed|failed|released` (the
`TransitionType` backing values). `since`/`until` are ISO-8601. All criteria are
optional; an absent key means "no constraint" (null), preserved through the codec.

### Envelope

Success:

```json
{ "data": <object | array>, "meta": { } }
```

Error:

```json
{ "error": { "type": "validation|forbidden|not_found|unauthenticated",
             "message": "human readable",
             "details": { } } }
```

Lists are always JSON arrays; an empty-but-valid result is `data: []` with `200`,
never an error.

### Per-endpoint payloads

- `POST /search` -> `data`: `JobSummary[]`, `meta`: `{ total, limit, offset }`.
  `total` is `JobQueryService::count(filter)` (the full match size, ignoring paging);
  `limit`/`offset` echo the filter's paging.
- `GET /jobs/{jobUuid}/history` -> `data`: `TransitionRecord[]`,
  `meta`: `{ jobUuid, count }`. Every recorded job has at least a `QUEUED`
  transition, so an empty history means the uuid was never recorded: respond `404`
  `not_found`, not an empty list.
- `POST /failures` -> `data`: `FailureGroup[]`, `meta`: `{ count }`. The core's
  `groupFailures` returns all fingerprint groups for the window and does not apply
  `limit`/`offset`, so failures is not offset-paginated; `count` is the number of
  groups returned.
- saved-search / alert-rule single resource -> `data`: the object.
- saved-search / alert-rule list -> `data`: array, `meta`: `{ count }`.

### DTO field shapes

All keys camelCase; timestamps ISO-8601 strings or null; enums as their string value.

- **JobSummary**: `jobUuid, jobClass, queue, tenantId, status, attempts,
  dispatchedAt, finishedAt, durationMs, exceptionFingerprint, inFlight`.
  `inFlight` is the derived `JobSummary::isInFlight()` (no finish time).
- **TransitionRecord**: `jobUuid, sequence, transition, attempt, occurredAt,
  durationMs, exceptionFingerprint, exceptionMessage`.
- **FailureGroup**: `fingerprint, representativeMessage, count, firstSeen, lastSeen`.
- **SavedSearch**: `id, name, filter` (the filter object above, via
  `FilterCodec::encode()`), `createdAt, updatedAt`.
- **AlertRule**: `id, savedSearchId, name, threshold, windowSeconds, cooldownSeconds,
  sinks, enabled, state, lastNotifiedAt, lastResultCount, lastEvaluatedAt, createdAt,
  updatedAt`. `state` is `ok|breaching`; `sinks` is a string array.

### Request bodies for writes

- `POST /saved-searches`, `PUT /saved-searches/{id}`: `{ name: string, filter: <filter object> }`.
- `POST /saved-searches/{id}/alert-rules`: `{ name, threshold, windowSeconds,
  cooldownSeconds, sinks: string[], enabled?: bool }`.
- `PUT /alert-rules/{id}`: same as create minus the saved-search id (path-bound);
  `enabled` required. Runtime state fields are never accepted from the client (the
  core's `AlertService` cannot set them).

### Status codes

`200` reads and updates, `201` create, `204` delete, `403` gate denial, `404`
unknown id/uuid, `422` validation failure.

## Auth seam implementation

- **Authentication**: routes are wrapped in `config('argus-api.middleware')`, default
  `['auth:sanctum']`, overridable by publishing the config. The `auth:sanctum` alias
  resolves only if the app installed Sanctum; that is the documented expectation, and
  an app using a different guard replaces the array. An unauthenticated request is
  rejected by this middleware before any controller runs.
- **Authorization**: `AuthorizationServiceProvider` defines the four gates. Each gate
  defaults to returning `true` for any authenticated user, carrying a prominent
  comment instructing the app to tighten it. Gates are registered only when the app
  has not already defined them (guarded by `Gate::has()`), so app definitions win.
  The provider and a gates file are publishable so the app can edit defaults in
  place.
- Controllers check `Gate::denies($ability)` and return the package's own `403`
  envelope directly, rather than relying on the app's global exception handler, so
  the error shape stays consistent.

### Error rendering without touching the app handler

- Validation: a base `ApiFormRequest` overrides `failedValidation()` to throw an
  `HttpResponseException` carrying the `422` envelope. Self-contained; no global
  handler registration.
- Not found: a package `NotFoundException` renders its own `404` envelope.
- Unauthenticated (`401`): owned by the app's auth middleware. Its response shape is
  the app's, by design, since authentication is the app's responsibility.

## Typed contract for Phase 5

The README ships an API reference (endpoints, request bodies, response shapes, status
codes) plus a TypeScript declaration block mirroring the DTO and envelope shapes, so
the Phase 5 React client builds against a stable spec.

## Testing (feature tests against the HTTP layer)

1. Each endpoint returns the expected envelope and shape for a seeded dataset. Seed
   by writing transitions through the core's store inside the package `TestCase`,
   then read through the API.
2. Unauthenticated request -> rejected. Configure a real `auth` middleware on the
   routes and hit with no authenticated user.
3. Authenticated but missing the gate -> `403` envelope. Override a gate to deny.
4. `POST /search` with a structured filter returns exactly the matching jobs,
   paginated: assert `data` contains only matches and `meta.total` reflects the full
   match set across pages.
5. Unknown `jobUuid` -> `404`; empty search result set -> `200` with `data: []`.
6. Storage isolation: bind mock `JobQueryService`/`SavedSearchService`/`AlertService`
   into the container, assert controllers invoke them with the decoded filter and
   build responses from their return values. Plus a static scan asserting `src/`
   references no `Illuminate\Database`, no `DB`, and no core `Argus\Storage\` symbol.

## Out of scope

- No React app (Phase 5).
- No authentication (login, tokens, sessions) — owned by the consuming app.
- No new storage, SQL, or table access — all reads/writes go through core services.
