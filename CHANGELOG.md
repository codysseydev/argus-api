# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-06-28

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

[Unreleased]: https://github.com/codysseydev/argus-api/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/codysseydev/argus-api/releases/tag/v1.0.0
