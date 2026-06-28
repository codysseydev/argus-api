# API Reference

All paths below are relative to the configured prefix (default `argus-api`).
All responses use the envelope described in the [Response envelope](#response-envelope) section.
All dates and times are ISO-8601 strings. All object keys are camelCase.

## Response envelope

**Success**

```json
{ "data": <object | array>, "meta": {} }
```

`meta` is always a JSON object. Lists are always JSON arrays; an empty result is
`data: []` with status `200`.

**Error**

```json
{
  "error": {
    "type": "validation | forbidden | not_found",
    "message": "human readable string",
    "details": {}
  }
}
```

## Status codes

| Code | Meaning |
|------|---------|
| 200 | Read or update succeeded |
| 201 | Resource created |
| 204 | Resource deleted (no body) |
| 401 | Not authenticated (shape owned by the app's auth middleware) |
| 403 | Authenticated but gate denied |
| 404 | Referenced resource does not exist |
| 422 | Validation failed |

## Filter object

The filter is the shared input shape for the search and failure endpoints (as the
request body) and for saved searches (as the `filter` key in the request body).
Every field is optional; omitting a field means "no constraint".

| Field | Type | Notes |
|-------|------|-------|
| `jobClass` | string \| null | Exact match on the job class name |
| `queue` | string \| null | Exact match on the queue name |
| `tenantId` | string \| null | Exact match on the tenant identifier |
| `status` | string \| null | One of: `queued`, `processing`, `processed`, `failed`, `released` |
| `attemptMin` | integer \| null | Minimum attempt count (inclusive, >= 0) |
| `attemptMax` | integer \| null | Maximum attempt count (inclusive, >= 0) |
| `since` | ISO-8601 \| null | Lower bound on event time |
| `until` | ISO-8601 \| null | Upper bound on event time |
| `correlationKey` | string \| null | Required when `correlationValue` is present |
| `correlationValue` | string \| null | Required when `correlationKey` is present |
| `limit` | integer | Page size. Default `100`, clamped to `500` maximum. |
| `offset` | integer | Page offset. Default `0`. |

`correlationKey` and `correlationValue` must be provided together; providing one
without the other returns a `422`.

## Resource shapes

### JobSummary

| Field | Type | Notes |
|-------|------|-------|
| `jobUuid` | string | Unique identifier for the job |
| `jobClass` | string | Fully qualified job class name |
| `queue` | string | Queue name |
| `tenantId` | string \| null | |
| `status` | string | Most recent transition type |
| `attempts` | integer | Total attempt count |
| `dispatchedAt` | ISO-8601 \| null | |
| `finishedAt` | ISO-8601 \| null | |
| `durationMs` | integer \| null | Milliseconds from queued to finished |
| `exceptionFingerprint` | string \| null | Set when the last transition was a failure |
| `inFlight` | boolean | True when the job has no finish time |

### TransitionRecord

| Field | Type | Notes |
|-------|------|-------|
| `jobUuid` | string | |
| `sequence` | integer | Ordinal position within the job's history |
| `transition` | string | One of: `queued`, `processing`, `processed`, `failed`, `released` |
| `attempt` | integer | Attempt number at the time of this transition |
| `occurredAt` | ISO-8601 | |
| `durationMs` | integer \| null | Only set on terminal transitions |
| `exceptionFingerprint` | string \| null | Only set on `failed` transitions |
| `exceptionMessage` | string \| null | Only set on `failed` transitions |

### FailureGroup

| Field | Type | Notes |
|-------|------|-------|
| `fingerprint` | string | Identifies the exception type |
| `representativeMessage` | string \| null | Message from the most recent occurrence |
| `count` | integer | Total failure count for this fingerprint |
| `firstSeen` | ISO-8601 | |
| `lastSeen` | ISO-8601 | |

### SavedSearch

| Field | Type | Notes |
|-------|------|-------|
| `id` | string | |
| `name` | string | |
| `filter` | Filter object | See [Filter object](#filter-object) |
| `createdAt` | ISO-8601 | |
| `updatedAt` | ISO-8601 | |

### AlertRule

| Field | Type | Notes |
|-------|------|-------|
| `id` | string | |
| `savedSearchId` | string | The saved search this rule evaluates |
| `name` | string | |
| `threshold` | integer | Job count that triggers the alert |
| `windowSeconds` | integer | Evaluation window length in seconds |
| `cooldownSeconds` | integer | Minimum seconds between repeated notifications |
| `sinks` | string[] | Notification sink identifiers |
| `enabled` | boolean | |
| `state` | string | `ok` or `breaching` |
| `lastNotifiedAt` | ISO-8601 \| null | |
| `lastResultCount` | integer \| null | Result count from the last evaluation |
| `lastEvaluatedAt` | ISO-8601 \| null | |
| `createdAt` | ISO-8601 | |
| `updatedAt` | ISO-8601 | |

---

## Search

### `POST /search`

Gate: `view-jobs`

Runs a filter against current-state jobs.

**Request body:** [Filter object](#filter-object) (all fields optional)

**Response `200`**

```json
{
  "data": [JobSummary, ...],
  "meta": { "total": 42, "limit": 100, "offset": 0 }
}
```

`meta.total` is the full match count across all pages, not just the current page.
`meta.limit` and `meta.offset` echo the effective pagination values.

---

## Job history

### `GET /jobs/{jobUuid}/history`

Gate: `view-jobs`

Returns the ordered lifecycle of a single job.

**Path parameter:** `jobUuid` — the job identifier.

**Response `200`**

```json
{
  "data": [TransitionRecord, ...],
  "meta": { "jobUuid": "...", "count": 3 }
}
```

**Response `404`** when the `jobUuid` has no recorded transitions (every known job has
at least a `queued` transition, so an empty result means the UUID was never observed).

---

## Failures

### `POST /failures`

Gate: `view-failures`

Returns failures grouped by exception fingerprint. The filter's `limit`/`offset`
fields are accepted but grouping is not offset-paginated; `meta.count` is the total
number of groups returned.

**Request body:** [Filter object](#filter-object) (all fields optional)

**Response `200`**

```json
{
  "data": [FailureGroup, ...],
  "meta": { "count": 5 }
}
```

---

## Saved searches

All saved-search endpoints require gate: `manage-saved-searches`, except
`GET /saved-searches/{id}/results` which requires `view-jobs`.

### `GET /saved-searches`

Returns all saved searches.

**Response `200`**

```json
{
  "data": [SavedSearch, ...],
  "meta": { "count": 3 }
}
```

### `POST /saved-searches`

Creates a saved search.

**Request body**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `name` | string | Yes | Max 255 characters |
| `filter` | object | Yes | A [Filter object](#filter-object); may be `{}` for a match-all search |

**Response `201`**

```json
{ "data": SavedSearch, "meta": {} }
```

### `GET /saved-searches/{id}`

Returns a single saved search.

**Response `200`** / **`404`** if `id` is unknown.

```json
{ "data": SavedSearch, "meta": {} }
```

### `PUT /saved-searches/{id}`

Replaces the `name` and `filter` of an existing saved search.

**Request body:** same shape as `POST /saved-searches`.

**Response `200`** / **`404`** if `id` is unknown.

```json
{ "data": SavedSearch, "meta": {} }
```

### `DELETE /saved-searches/{id}`

Deletes a saved search.

**Response `204`** / **`404`** if `id` is unknown.

### `GET /saved-searches/{id}/results`

Gate: `view-jobs`

Runs the saved search's stored filter and returns matching jobs.

**Response `200`** / **`404`** if `id` is unknown.

```json
{
  "data": [JobSummary, ...],
  "meta": { "savedSearchId": "...", "count": 12 }
}
```

---

## Alert rules

All alert-rule endpoints require gate: `manage-alerts`.

### `GET /saved-searches/{savedSearchId}/alert-rules`

Lists alert rules attached to a specific saved search.

**Response `200`** / **`404`** if `savedSearchId` is unknown.

```json
{
  "data": [AlertRule, ...],
  "meta": { "savedSearchId": "...", "count": 2 }
}
```

### `POST /saved-searches/{savedSearchId}/alert-rules`

Creates an alert rule attached to a saved search.

**Path parameter:** `savedSearchId`

**Request body**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `name` | string | Yes | Max 255 characters |
| `threshold` | integer | Yes | >= 0; job count that triggers the alert |
| `windowSeconds` | integer | Yes | >= 1; evaluation window length |
| `cooldownSeconds` | integer | Yes | >= 0; minimum seconds between notifications |
| `sinks` | string[] | Yes | May be empty array |
| `enabled` | boolean | No | Defaults to `true` |

**Response `201`** / **`404`** if `savedSearchId` is unknown.

```json
{ "data": AlertRule, "meta": {} }
```

### `GET /alert-rules`

Lists all alert rules across all saved searches.

**Response `200`**

```json
{
  "data": [AlertRule, ...],
  "meta": { "count": 4 }
}
```

### `GET /alert-rules/{id}`

Returns a single alert rule.

**Response `200`** / **`404`** if `id` is unknown.

```json
{ "data": AlertRule, "meta": {} }
```

### `PUT /alert-rules/{id}`

Updates an existing alert rule.

**Request body:** same shape as `POST /saved-searches/{savedSearchId}/alert-rules`.
When `enabled` is omitted, the existing value is preserved.

**Response `200`** / **`404`** if `id` is unknown.

```json
{ "data": AlertRule, "meta": {} }
```

### `DELETE /alert-rules/{id}`

Deletes an alert rule.

**Response `204`** / **`404`** if `id` is unknown.
