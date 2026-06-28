# Argus API HTTP Reference

## Base prefix and authentication

All paths below are relative to the configured prefix (default: `argus-api`).
A full path therefore looks like `/argus-api/search`.

Authentication is handled by the middleware stack in `config('argus-api.middleware')`,
which defaults to `['auth:sanctum']`. You can replace this with any guard. Unauthenticated
requests are rejected before any controller runs (`401`).

Authorization is enforced by four gates: `view-jobs`, `view-failures`,
`manage-saved-searches`, `manage-alerts`. By default every authenticated user is allowed
through all gates. To restrict access, set `argus-api.authorization.allow_by_default`
to `false` and define your own gate implementations (see the publishable provider stub).

---

## Response envelopes

### Success

```json
{ "data": <object | array>, "meta": {} }
```

`meta` is always a JSON object (never an array). For list endpoints it contains
contextual counts and identifiers. For create and delete endpoints the `meta` object
is empty.

### Failure

```json
{ "error": { "type": "...", "message": "...", "details": {} } }
```

| `type` value      | HTTP status |
|-------------------|-------------|
| `unauthenticated` | 401         |
| `forbidden`       | 403         |
| `not_found`       | 404         |
| `validation`      | 422         |

`details` holds field-level validation errors when `type` is `validation`; it is an
empty object otherwise.

An empty list result is `data: []` with `200` (not `404`).

---

## Filter object

Several endpoints accept a filter object. All fields are optional.
`correlationKey` and `correlationValue` must be supplied together or not at all.

| Field              | Type     | Notes                                                   |
|--------------------|----------|---------------------------------------------------------|
| `jobClass`         | string\|null | Fully-qualified job class name                      |
| `queue`            | string\|null | Queue name                                          |
| `tenantId`         | string\|null | Tenant identifier                                   |
| `status`           | string\|null | One of `queued`, `processing`, `processed`, `failed`, `released` |
| `attemptMin`       | integer\|null | Minimum attempt count (inclusive)                  |
| `attemptMax`       | integer\|null | Maximum attempt count (inclusive)                  |
| `since`            | ISO 8601\|null | Lower bound on the recorded timestamp             |
| `until`            | ISO 8601\|null | Upper bound on the recorded timestamp             |
| `correlationKey`   | string\|null | Correlation key name                               |
| `correlationValue` | string\|null | Correlation key value                              |
| `limit`            | integer\|null | Max results to return                              |
| `offset`           | integer\|null | Result offset for pagination                       |

---

## Resource shapes

### JobSummary

| Field                | Type            |
|----------------------|-----------------|
| `jobUuid`            | string          |
| `jobClass`           | string          |
| `queue`              | string          |
| `tenantId`           | string\|null    |
| `status`             | string          |
| `attempts`           | integer         |
| `dispatchedAt`       | ISO 8601\|null  |
| `finishedAt`         | ISO 8601\|null  |
| `durationMs`         | integer\|null   |
| `exceptionFingerprint` | string\|null  |
| `inFlight`           | boolean         |

### TransitionRecord

| Field                | Type     |
|----------------------|----------|
| `jobUuid`            | string   |
| `sequence`           | integer  |
| `transition`         | string   |
| `attempt`            | integer  |
| `occurredAt`         | ISO 8601 |
| `durationMs`         | integer\|null |
| `exceptionFingerprint` | string\|null |
| `exceptionMessage`   | string\|null  |

### FailureGroup

| Field                  | Type     |
|------------------------|----------|
| `fingerprint`          | string   |
| `representativeMessage`| string\|null |
| `count`                | integer  |
| `firstSeen`            | ISO 8601 |
| `lastSeen`             | ISO 8601 |

### SavedSearch

| Field       | Type     |
|-------------|----------|
| `id`        | string   |
| `name`      | string   |
| `filter`    | Filter   |
| `createdAt` | ISO 8601 |
| `updatedAt` | ISO 8601 |

### AlertRule

| Field              | Type            |
|--------------------|-----------------|
| `id`               | string          |
| `savedSearchId`    | string          |
| `name`             | string          |
| `threshold`        | integer         |
| `conditionType`    | string          |
| `comparison`       | string          |
| `stuckSeconds`     | integer\|null   |
| `windowSeconds`    | integer         |
| `cooldownSeconds`  | integer         |
| `sinks`            | string[]        |
| `enabled`          | boolean         |
| `state`            | string (`ok` or `breaching`) |
| `lastNotifiedAt`   | ISO 8601\|null  |
| `lastResultCount`  | integer\|null   |
| `lastEvaluatedAt`  | ISO 8601\|null  |
| `createdAt`        | ISO 8601        |
| `updatedAt`        | ISO 8601        |

### AlertFiring

| Field           | Type     |
|-----------------|----------|
| `id`            | string   |
| `alertRuleId`   | string   |
| `conditionType` | string   |
| `observedValue` | mixed    |
| `threshold`     | mixed    |
| `windowSeconds` | integer  |
| `firedAt`       | ISO 8601 |

---

## Endpoints

### Search

#### `POST /search`

Gate: `view-jobs`

Request body: a [Filter object](#filter-object).

Returns all jobs matching the filter in their current state.

```json
{
  "data": [ JobSummary, ... ],
  "meta": { "total": 42, "limit": 100, "offset": 0 }
}
```

---

### Jobs

#### `GET /jobs/{jobUuid}/history`

Gate: `view-jobs`

| Path param | Type   | Notes               |
|------------|--------|---------------------|
| `jobUuid`  | string | UUID of the job     |

Returns the ordered lifecycle transitions for one job. Returns `404` if the UUID
has no recorded history.

```json
{
  "data": [ TransitionRecord, ... ],
  "meta": { "jobUuid": "...", "count": 3 }
}
```

---

### Failures

#### `POST /failures`

Gate: `view-failures`

Request body: a [Filter object](#filter-object).

Returns failures grouped by exception fingerprint.

```json
{
  "data": [ FailureGroup, ... ],
  "meta": { "count": 5 }
}
```

---

### Saved searches

All saved-search endpoints use gate `manage-saved-searches`, except
`GET /saved-searches/{id}/results` which uses `view-jobs`.

#### `GET /saved-searches`

Returns all saved searches.

```json
{
  "data": [ SavedSearch, ... ],
  "meta": { "count": 3 }
}
```

#### `POST /saved-searches`

Creates a saved search. Returns `201`.

Request body:

| Field    | Type   | Required | Notes                          |
|----------|--------|----------|--------------------------------|
| `name`   | string | yes      | max 255 characters             |
| `filter` | object | yes      | A [Filter object](#filter-object); may be empty (`{}`) |

```json
{ "data": SavedSearch, "meta": {} }
```

#### `GET /saved-searches/{id}`

| Path param | Type   |
|------------|--------|
| `id`       | string |

Returns one saved search. Returns `404` if the id is unknown.

```json
{ "data": SavedSearch, "meta": {} }
```

#### `PUT /saved-searches/{id}`

| Path param | Type   |
|------------|--------|
| `id`       | string |

Request body: same shape as `POST /saved-searches`.

Returns `404` if the id is unknown.

```json
{ "data": SavedSearch, "meta": {} }
```

#### `DELETE /saved-searches/{id}`

| Path param | Type   |
|------------|--------|
| `id`       | string |

Returns `204` with no body. Returns `404` if the id is unknown.

#### `GET /saved-searches/{id}/results`

Gate: `view-jobs`

| Path param | Type   |
|------------|--------|
| `id`       | string |

Runs the saved search's stored filter and returns matching jobs. Returns `404` if
the saved search is unknown.

```json
{
  "data": [ JobSummary, ... ],
  "meta": { "savedSearchId": "...", "count": 10 }
}
```

---

### Alert rules

All alert-rule endpoints use gate `manage-alerts`.

#### `GET /saved-searches/{savedSearchId}/alert-rules`

| Path param      | Type   |
|-----------------|--------|
| `savedSearchId` | string |

Returns all alert rules attached to the given saved search. Returns `404` if
the saved search is unknown.

```json
{
  "data": [ AlertRule, ... ],
  "meta": { "savedSearchId": "...", "count": 2 }
}
```

#### `POST /saved-searches/{savedSearchId}/alert-rules`

| Path param      | Type   |
|-----------------|--------|
| `savedSearchId` | string |

Creates an alert rule attached to the given saved search. Returns `201`. Returns
`404` if the saved search is unknown.

Request body:

| Field            | Type     | Required | Notes                                                              |
|------------------|----------|----------|--------------------------------------------------------------------|
| `name`           | string   | yes      | max 255 characters                                                 |
| `threshold`      | integer  | yes      | min 0                                                              |
| `windowSeconds`  | integer  | yes      | min 1; evaluation window duration                                  |
| `cooldownSeconds`| integer  | yes      | min 0; silence period after a firing                               |
| `sinks`          | string[] | yes      | alert sink identifiers; may be empty                               |
| `conditionType`  | string   | no       | defaults to `count`; see values below                              |
| `comparison`     | string   | no       | defaults to `greater_than`; see values below                       |
| `stuckSeconds`   | integer\|null | no  | min 1; required when `conditionType` is `stuck`                    |
| `enabled`        | boolean  | no       | defaults to `true`                                                 |

`conditionType` values: `count`, `failure_rate`, `stuck`, `latency_p95` (backed by `AlertConditionType` enum in the core).

`comparison` values: `greater_than`, `less_than`, `greater_than_or_equal`, `less_than_or_equal` (backed by `AlertComparison` enum in the core).

```json
{ "data": AlertRule, "meta": {} }
```

#### `GET /alert-rules`

Returns all alert rules across all saved searches.

```json
{
  "data": [ AlertRule, ... ],
  "meta": { "count": 5 }
}
```

#### `GET /alert-rules/{id}`

| Path param | Type   |
|------------|--------|
| `id`       | string |

Returns one alert rule. Returns `404` if the id is unknown.

```json
{ "data": AlertRule, "meta": {} }
```

#### `PUT /alert-rules/{id}`

| Path param | Type   |
|------------|--------|
| `id`       | string |

Request body: same shape as `POST /saved-searches/{savedSearchId}/alert-rules`.
Fields `conditionType`, `comparison`, and `stuckSeconds` default to the rule's
current values when omitted.

Returns `404` if the id is unknown.

```json
{ "data": AlertRule, "meta": {} }
```

#### `DELETE /alert-rules/{id}`

| Path param | Type   |
|------------|--------|
| `id`       | string |

Returns `204` with no body. Returns `404` if the id is unknown.

---

### Alert firings

All alert-firing endpoints use gate `manage-alerts`.

#### `GET /alert-firings`

| Query param | Type    | Default | Max |
|-------------|---------|---------|-----|
| `limit`     | integer | 100     | 500 |

Returns the most recent alert firings across all rules, newest first.

```json
{
  "data": [ AlertFiring, ... ],
  "meta": { "count": 10 }
}
```

#### `GET /alert-rules/{id}/firings`

| Path param | Type   |
|------------|--------|
| `id`       | string |

| Query param | Type    | Default | Max |
|-------------|---------|---------|-----|
| `limit`     | integer | 100     | 500 |

Returns firings for one alert rule. Returns `404` if the rule id is unknown.

```json
{
  "data": [ AlertFiring, ... ],
  "meta": { "alertRuleId": "...", "count": 4 }
}
```

---

## Status code summary

| Code | When                                        |
|------|---------------------------------------------|
| 200  | Successful read or update                   |
| 201  | Successful create                           |
| 204  | Successful delete (no body)                 |
| 401  | Unauthenticated (no valid session/token)    |
| 403  | Authenticated but gate denied               |
| 404  | Unknown path parameter (id / uuid)          |
| 422  | Request body failed validation              |
