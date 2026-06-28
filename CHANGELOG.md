# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0] - 2026-06-28

### Added

- `GET /alert-firings` endpoint returning recent alert firings across every rule.
- `GET /alert-rules/{id}/firings` endpoint returning the firing history for a single rule.
- `AlertFiringResource` serialising the append-only firing log (observed value, threshold, window, fired-at).
- Alert-rule condition fields exposed over HTTP: `conditionType` (count, failure_rate, stuck_count, latency_p95), `comparison` (gt, lt), and `stuckSeconds`. Returned by `AlertRuleResource`, validated by `AlertRuleRequest`, and applied by the create/update controllers.
- `docs/api.md`: full HTTP endpoint reference, linked from the README.

### Changed

- `stuckSeconds` is now required when an alert rule's condition is `stuck_count`, and rejected for any other condition.

## [0.1.0] - 2026-06-28

### Added

- JSON HTTP API exposing the `codysseydev/argus` queue-observability query service.
- `POST /search` endpoint for querying current-state jobs by filter (job class, queue,
  tenant, status, attempt range, time window, correlation key/value).
- `GET /jobs/{jobUuid}/history` endpoint for the ordered lifecycle of a single job.
- `POST /failures` endpoint returning failures grouped by exception fingerprint.
- Saved-search CRUD: `GET`, `POST`, `GET /{id}`, `PUT /{id}`, `DELETE /{id}`, and
  `GET /{id}/results` under `/saved-searches`.
- Alert-rule CRUD: rules nested under `/saved-searches/{id}/alert-rules` and a flat
  listing at `/alert-rules`, `GET /alert-rules/{id}`, `PUT`, `DELETE`.
- Configurable auth guard seam: `argus-api.guard` and `argus-api.middleware` config
  keys let the host application plug in any Laravel authentication guard.
- Authorization gates (`view-jobs`, `view-failures`, `manage-saved-searches`,
  `manage-alerts`) with a publishable provider stub for tightening access control.
- Consistent JSON envelope: `{ data, meta }` for success and
  `{ error: { type, message, details } }` for errors.
- Support for Laravel 12 and 13.
- Published config (`argus-api-config`) and authorization stub
  (`argus-api-authorization`).

[Unreleased]: https://github.com/codysseydev/argus-api/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/codysseydev/argus-api/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/codysseydev/argus-api/releases/tag/v0.1.0
