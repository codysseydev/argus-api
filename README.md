# Argus API (`codysseydev/argus-api`)

[![Packagist Version](https://img.shields.io/packagist/v/codysseydev/argus-api)](https://packagist.org/packages/codysseydev/argus-api)
[![Packagist Downloads](https://img.shields.io/packagist/dt/codysseydev/argus-api)](https://packagist.org/packages/codysseydev/argus-api)
[![License](https://img.shields.io/packagist/l/codysseydev/argus-api)](LICENSE)
[![Tests](https://github.com/codysseydev/argus-api/actions/workflows/tests.yml/badge.svg)](https://github.com/codysseydev/argus-api/actions/workflows/tests.yml)

A JSON HTTP API over the [`codysseydev/argus`](../argus) queue-observability
core. It exposes the core's read and management services and nothing else: it holds
no storage knowledge, emits no SQL, and never touches a database. Dependency
direction is one way: `React client -> this API -> codysseydev/argus -> storage`.

## Installation

```bash
composer require codysseydev/argus-api
php artisan vendor:publish --tag=argus-api-config
```

Optionally publish an editable authorization provider:

```bash
php artisan vendor:publish --tag=argus-api-authorization
# then register App\Providers\ArgusApiAuthorizationServiceProvider
```

## Authentication and authorization

- **Authentication is the app's job.** Routes mount behind the middleware stack in
  `config('argus-api.middleware')`, defaulting to `['auth:sanctum']`. Replace it
  with your own guard. Unauthenticated requests are rejected before any controller
  runs (`401`).
- **Authorization is this package's job**, via four gates: `view-jobs`,
  `view-failures`, `manage-saved-searches`, `manage-alerts`. Each defaults to
  allowing any authenticated user. Tighten by setting
  `argus-api.authorization.allow_by_default` to `false`, or by defining the gates
  in your own provider (see the published stub).

## Envelope

Success:

```json
{ "data": <object | array>, "meta": { } }
```

Error:

```json
{ "error": { "type": "validation|forbidden|not_found|unauthenticated", "message": "...", "details": { } } }
```

Lists are always arrays; an empty result is `data: []` with `200`.

## Endpoints

All paths are relative to the configured prefix (default `argus-api`).

### `POST /search` (gate: `view-jobs`)

Body: a filter object (see Filter below). Returns current-state jobs.

```json
{ "data": [ JobSummary, ... ], "meta": { "total": 42, "limit": 100, "offset": 0 } }
```

### `GET /jobs/{jobUuid}/history` (gate: `view-jobs`)

Ordered lifecycle of one job. Unknown uuid → `404`.

```json
{ "data": [ TransitionRecord, ... ], "meta": { "jobUuid": "...", "count": 3 } }
```

### `POST /failures` (gate: `view-failures`)

Body: a filter object. Returns failures grouped by exception fingerprint.

```json
{ "data": [ FailureGroup, ... ], "meta": { "count": 5 } }
```

### Saved searches (gate: `manage-saved-searches`; `results` is `view-jobs`)

| Method | Path | Body | Returns |
|---|---|---|---|
| GET | `/saved-searches` | — | `{ data: SavedSearch[], meta: { count } }` |
| POST | `/saved-searches` | `{ name, filter }` | `201 { data: SavedSearch }` |
| GET | `/saved-searches/{id}` | — | `{ data: SavedSearch }` (404 if unknown) |
| PUT | `/saved-searches/{id}` | `{ name, filter }` | `{ data: SavedSearch }` |
| DELETE | `/saved-searches/{id}` | — | `204` |
| GET | `/saved-searches/{id}/results` | — | `{ data: JobSummary[], meta: { savedSearchId, count } }` |

### Alert rules (gate: `manage-alerts`)

| Method | Path | Body | Returns |
|---|---|---|---|
| GET | `/saved-searches/{id}/alert-rules` | — | `{ data: AlertRule[], meta: { savedSearchId, count } }` |
| POST | `/saved-searches/{id}/alert-rules` | rule body | `201 { data: AlertRule }` (404 if saved search unknown) |
| GET | `/alert-rules` | — | `{ data: AlertRule[], meta: { count } }` |
| GET | `/alert-rules/{id}` | — | `{ data: AlertRule }` (404 if unknown) |
| PUT | `/alert-rules/{id}` | rule body | `{ data: AlertRule }` |
| DELETE | `/alert-rules/{id}` | — | `204` |

Rule body: `{ name, threshold, windowSeconds, cooldownSeconds, sinks: string[], enabled?: boolean }`.

## Status codes

`200` reads/updates · `201` create · `204` delete · `403` gate denial · `404`
unknown id/uuid · `422` validation.

## TypeScript contract (Phase 5)

```typescript
export type Iso8601 = string;
export type TransitionType = "queued" | "processing" | "processed" | "failed" | "released";
export type AlertState = "ok" | "breaching";

export interface Filter {
  jobClass?: string | null;
  queue?: string | null;
  tenantId?: string | null;
  status?: TransitionType | null;
  attemptMin?: number | null;
  attemptMax?: number | null;
  since?: Iso8601 | null;
  until?: Iso8601 | null;
  correlationKey?: string | null;
  correlationValue?: string | null;
  limit?: number;
  offset?: number;
}

export interface JobSummary {
  jobUuid: string;
  jobClass: string;
  queue: string;
  tenantId: string | null;
  status: string;
  attempts: number;
  dispatchedAt: Iso8601 | null;
  finishedAt: Iso8601 | null;
  durationMs: number | null;
  exceptionFingerprint: string | null;
  inFlight: boolean;
}

export interface TransitionRecord {
  jobUuid: string;
  sequence: number;
  transition: TransitionType;
  attempt: number;
  occurredAt: Iso8601;
  durationMs: number | null;
  exceptionFingerprint: string | null;
  exceptionMessage: string | null;
}

export interface FailureGroup {
  fingerprint: string;
  representativeMessage: string | null;
  count: number;
  firstSeen: Iso8601;
  lastSeen: Iso8601;
}

export interface SavedSearch {
  id: string;
  name: string;
  filter: Filter;
  createdAt: Iso8601;
  updatedAt: Iso8601;
}

export interface AlertRule {
  id: string;
  savedSearchId: string;
  name: string;
  threshold: number;
  windowSeconds: number;
  cooldownSeconds: number;
  sinks: string[];
  enabled: boolean;
  state: AlertState;
  lastNotifiedAt: Iso8601 | null;
  lastResultCount: number | null;
  lastEvaluatedAt: Iso8601 | null;
  createdAt: Iso8601;
  updatedAt: Iso8601;
}

export interface Envelope<T> { data: T; meta: Record<string, unknown>; }
export interface ApiError { error: { type: string; message: string; details: Record<string, unknown>; }; }
```

## Development

```bash
composer install
./vendor/bin/phpunit
./vendor/bin/pint
```

Tests use in-memory fakes for all storage contracts, so no database is required.
A running Redis is required because `ArgusServiceProvider` opens a Redis buffer
connection at boot.

To develop against an unreleased local checkout of the core, wire a path
repository temporarily (do not commit this change):

```bash
composer config repositories.argus path ../argus
composer update codysseydev/argus
```

See [CONTRIBUTING.md](CONTRIBUTING.md) and [RELEASING.md](RELEASING.md) for full
details. Related packages: [`codysseydev/argus`](../argus) (core),
[`codysseydev/argus-ui`](../argus-ui) (React UI).
