# Argus API (Phase 4) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `acme/queue-observability-api`, a standalone Laravel package that exposes the core `acme/argus` query and management services over a JSON HTTP API with a configurable auth-middleware seam and publishable authorization gates.

**Architecture:** Single-action `final readonly` controllers call only the core services (`JobQueryService`, `SavedSearchService`, `AlertService`); they never touch storage. Requests map JSON to the core's `JobFilter` via the core's own `FilterCodec`. Responses use a fixed `{data, meta}` / `{error}` envelope built by an `ApiResponse` helper. The app supplies authentication via an overridable middleware stack; this package supplies authorization via four Laravel gates that default to allow-authenticated.

**Tech Stack:** PHP 8.5, Laravel/Illuminate 13, Orchestra Testbench 11, PHPUnit 12, Laravel Pint. Tests run with in-memory fakes bound at the core's storage contracts, so no Postgres or Redis is required.

**Working directory for every command:** `/Users/davorminchorov/Code/GitHub/queue-observability-api`

**Conventions (match the core package):** `declare(strict_types=1);` in every PHP file; `final readonly class` for controllers, resources, support classes; constructor property promotion; explicit types; no facades except in the service provider and the routes file; enum/const cases UPPER_CASE.

---

## File Structure

```
queue-observability-api/
├── composer.json                 # Task 1
├── phpunit.xml.dist              # Task 1
├── pint.json                     # Task 1
├── config/argus-api.php          # Task 3
├── routes/argus-api.php          # Task 8 (created), extended through Task 11
├── stubs/argus-api-authorization-provider.stub   # Task 5
├── src/
│   ├── ArgusApiServiceProvider.php                # Task 3, extended Task 5
│   ├── Authorization/Abilities.php                # Task 5
│   ├── Authorization/AuthorizationServiceProvider.php  # Task 5
│   ├── Authorization/Authorize.php                # Task 5
│   ├── Exceptions/NotFoundException.php           # Task 4
│   ├── Exceptions/ForbiddenException.php           # Task 5
│   ├── Http/Support/ApiResponse.php               # Task 4
│   ├── Http/Support/FilterInput.php               # Task 6
│   ├── Http/Requests/ApiFormRequest.php           # Task 4
│   ├── Http/Requests/FilterRules.php              # Task 6
│   ├── Http/Requests/SearchRequest.php            # Task 6
│   ├── Http/Requests/FailureRequest.php           # Task 6
│   ├── Http/Requests/SaveSearchRequest.php        # Task 10
│   ├── Http/Requests/AlertRuleRequest.php         # Task 11
│   ├── Http/Resources/JobSummaryResource.php      # Task 7
│   ├── Http/Resources/TransitionRecordResource.php # Task 7
│   ├── Http/Resources/FailureGroupResource.php    # Task 7
│   ├── Http/Resources/SavedSearchResource.php     # Task 7
│   ├── Http/Resources/AlertRuleResource.php       # Task 7
│   └── Http/Controllers/
│       ├── SearchController.php                   # Task 8
│       ├── JobHistoryController.php               # Task 9
│       ├── FailureGroupController.php             # Task 9
│       ├── SavedSearches/*.php (6 controllers)    # Task 10
│       └── AlertRules/*.php (6 controllers)       # Task 11
└── tests/
    ├── TestCase.php                               # Task 2
    ├── Support/FakeTransitionQuery.php            # Task 2
    ├── Support/FakeSavedSearchStore.php           # Task 2
    ├── Support/FakeAlertRuleStore.php             # Task 2
    ├── Unit/ConfigTest.php                        # Task 3
    ├── Unit/FilterInputTest.php                   # Task 6
    ├── Unit/ResourcesTest.php                     # Task 7
    └── Feature/
        ├── SearchEndpointTest.php                 # Task 8
        ├── JobHistoryEndpointTest.php             # Task 9
        ├── FailureGroupEndpointTest.php           # Task 9
        ├── SavedSearchEndpointTest.php            # Task 10
        ├── AlertRuleEndpointTest.php              # Task 11
        ├── AuthorizationTest.php                  # Task 12
        ├── UnauthenticatedTest.php                # Task 12
        └── StorageIsolationTest.php               # Task 13
```

---

## Task 1: Package scaffold

**Files:**
- Create: `composer.json`
- Create: `phpunit.xml.dist`
- Create: `pint.json`

- [ ] **Step 1: Write `composer.json`**

```json
{
    "name": "acme/queue-observability-api",
    "description": "JSON HTTP API exposing the acme/argus queue-observability query service.",
    "type": "library",
    "license": "MIT",
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
    "autoload": {
        "psr-4": { "ArgusApi\\": "src/" }
    },
    "autoload-dev": {
        "psr-4": { "ArgusApi\\Tests\\": "tests/" }
    },
    "extra": {
        "laravel": {
            "providers": [
                "ArgusApi\\ArgusApiServiceProvider"
            ]
        }
    },
    "scripts": {
        "test": "phpunit"
    },
    "minimum-stability": "stable",
    "prefer-stable": true,
    "config": {
        "sort-packages": true
    }
}
```

- [ ] **Step 2: Write `pint.json`**

```json
{
    "preset": "laravel"
}
```

- [ ] **Step 3: Write `phpunit.xml.dist`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         failOnWarning="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

- [ ] **Step 4: Install dependencies**

Run: `composer install`
Expected: resolves and symlinks `acme/argus` from `../queue-observability` into `vendor/acme/argus`; finishes with "Generating autoload files".

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock pint.json phpunit.xml.dist
git commit -m "chore: scaffold acme/queue-observability-api package

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: Test harness and in-memory fakes

The whole suite runs against fakes bound at the core's storage contracts (`TransitionQuery`, `SavedSearchStore`, `AlertRuleStore`). The real core services (`JobQueryService`, `SavedSearchService`, `AlertService`) resolve on top of them, so the HTTP layer is exercised end to end with no database.

**Files:**
- Create: `tests/Support/FakeTransitionQuery.php`
- Create: `tests/Support/FakeSavedSearchStore.php`
- Create: `tests/Support/FakeAlertRuleStore.php`
- Create: `tests/TestCase.php`

- [ ] **Step 1: Write `tests/Support/FakeTransitionQuery.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Support;

use Argus\Contracts\TransitionQuery;
use Argus\Query\FailureGroup;
use Argus\Query\JobFilter;
use Argus\Query\JobSummary;
use Argus\Query\TransitionRecord;

/**
 * In-memory TransitionQuery for tests. Applies the JobFilter predicates so the
 * HTTP layer's filter mapping and pagination can be asserted end to end without
 * a database. Records the last filter it received so tests can prove the
 * controller delegated through the query seam. Correlation predicates are not
 * modelled (no endpoint test needs them); every other predicate is.
 */
final class FakeTransitionQuery implements TransitionQuery
{
    /** @var list<JobSummary> */
    public array $summaries = [];

    /** @var array<string, list<TransitionRecord>> */
    public array $histories = [];

    /** @var list<FailureGroup> */
    public array $failureGroups = [];

    public ?JobFilter $lastFilter = null;

    public function search(JobFilter $filter): array
    {
        $this->lastFilter = $filter;

        $matched = array_values(array_filter($this->summaries, fn (JobSummary $s) => $this->matches($s, $filter)));

        usort($matched, function (JobSummary $a, JobSummary $b): int {
            $av = $a->dispatchedAt?->getTimestamp();
            $bv = $b->dispatchedAt?->getTimestamp();
            if ($av === $bv) {
                return 0;
            }
            if ($av === null) {
                return 1;
            }
            if ($bv === null) {
                return -1;
            }

            return $bv <=> $av;
        });

        return array_slice($matched, $filter->offset, $filter->limit);
    }

    public function count(JobFilter $filter): int
    {
        $this->lastFilter = $filter;

        return count(array_filter($this->summaries, fn (JobSummary $s) => $this->matches($s, $filter)));
    }

    public function history(string $jobUuid): array
    {
        return $this->histories[$jobUuid] ?? [];
    }

    public function groupFailures(JobFilter $filter): array
    {
        $this->lastFilter = $filter;

        return array_values(array_filter($this->failureGroups, function (FailureGroup $g) use ($filter): bool {
            if ($filter->since !== null && $g->lastSeen->lessThan($filter->since)) {
                return false;
            }
            if ($filter->until !== null && $g->firstSeen->greaterThan($filter->until)) {
                return false;
            }

            return true;
        }));
    }

    private function matches(JobSummary $s, JobFilter $f): bool
    {
        if ($f->jobClass !== null && $s->jobClass !== $f->jobClass) {
            return false;
        }
        if ($f->queue !== null && $s->queue !== $f->queue) {
            return false;
        }
        if ($f->tenantId !== null && $s->tenantId !== $f->tenantId) {
            return false;
        }
        if ($f->status !== null && $s->status !== $f->status->value) {
            return false;
        }
        if ($f->attemptMin !== null && $s->attempts < $f->attemptMin) {
            return false;
        }
        if ($f->attemptMax !== null && $s->attempts > $f->attemptMax) {
            return false;
        }
        if ($f->since !== null && ($s->dispatchedAt === null || $s->dispatchedAt->lessThan($f->since))) {
            return false;
        }
        if ($f->until !== null && ($s->dispatchedAt === null || $s->dispatchedAt->greaterThan($f->until))) {
            return false;
        }

        return true;
    }
}
```

- [ ] **Step 2: Write `tests/Support/FakeSavedSearchStore.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Support;

use Argus\Contracts\SavedSearchStore;
use Argus\Query\JobFilter;
use Argus\SavedSearches\SavedSearch;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class FakeSavedSearchStore implements SavedSearchStore
{
    /** @var array<string, SavedSearch> */
    private array $items = [];

    private int $seq = 0;

    public function create(string $name, JobFilter $filter): SavedSearch
    {
        $id = (string) (++$this->seq);
        $now = CarbonImmutable::now();
        $saved = new SavedSearch($id, $name, $filter, $now, $now);
        $this->items[$id] = $saved;

        return $saved;
    }

    public function all(): array
    {
        return array_values($this->items);
    }

    public function find(string $id): ?SavedSearch
    {
        return $this->items[$id] ?? null;
    }

    public function update(string $id, string $name, JobFilter $filter): SavedSearch
    {
        $existing = $this->items[$id] ?? null;
        if ($existing === null) {
            throw new InvalidArgumentException("Unknown saved search [{$id}]");
        }
        $saved = new SavedSearch($id, $name, $filter, $existing->createdAt, CarbonImmutable::now());
        $this->items[$id] = $saved;

        return $saved;
    }

    public function delete(string $id): void
    {
        unset($this->items[$id]);
    }
}
```

- [ ] **Step 3: Write `tests/Support/FakeAlertRuleStore.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Support;

use Argus\Alerting\AlertRule;
use Argus\Alerting\AlertState;
use Argus\Contracts\AlertRuleStore;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class FakeAlertRuleStore implements AlertRuleStore
{
    /** @var array<string, AlertRule> */
    private array $items = [];

    private int $seq = 0;

    public function create(string $savedSearchId, string $name, int $threshold, int $windowSeconds, int $cooldownSeconds, array $sinks, bool $enabled): AlertRule
    {
        $id = (string) (++$this->seq);
        $now = CarbonImmutable::now();
        $rule = new AlertRule($id, $savedSearchId, $name, $threshold, $windowSeconds, $cooldownSeconds, $sinks, $enabled, AlertState::OK, null, null, null, $now, $now);
        $this->items[$id] = $rule;

        return $rule;
    }

    public function all(): array
    {
        return array_values($this->items);
    }

    public function enabled(): array
    {
        return array_values(array_filter($this->items, fn (AlertRule $r) => $r->enabled));
    }

    public function find(string $id): ?AlertRule
    {
        return $this->items[$id] ?? null;
    }

    public function forSavedSearch(string $savedSearchId): array
    {
        return array_values(array_filter($this->items, fn (AlertRule $r) => $r->savedSearchId === $savedSearchId));
    }

    public function update(string $id, string $name, int $threshold, int $windowSeconds, int $cooldownSeconds, array $sinks, bool $enabled): AlertRule
    {
        $existing = $this->items[$id] ?? null;
        if ($existing === null) {
            throw new InvalidArgumentException("Unknown alert rule [{$id}]");
        }
        $rule = new AlertRule($id, $existing->savedSearchId, $name, $threshold, $windowSeconds, $cooldownSeconds, $sinks, $enabled, $existing->state, $existing->lastNotifiedAt, $existing->lastResultCount, $existing->lastEvaluatedAt, $existing->createdAt, CarbonImmutable::now());
        $this->items[$id] = $rule;

        return $rule;
    }

    public function delete(string $id): void
    {
        unset($this->items[$id]);
    }

    public function recordEvaluation(AlertRule $rule): void
    {
        $this->items[$rule->id] = $rule;
    }
}
```

- [ ] **Step 4: Write `tests/TestCase.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Tests;

use Argus\ArgusServiceProvider;
use Argus\Contracts\AlertRuleStore;
use Argus\Contracts\SavedSearchStore;
use Argus\Contracts\TransitionQuery;
use ArgusApi\ArgusApiServiceProvider;
use ArgusApi\Tests\Support\FakeAlertRuleStore;
use ArgusApi\Tests\Support\FakeSavedSearchStore;
use ArgusApi\Tests\Support\FakeTransitionQuery;
use Illuminate\Auth\GenericUser;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected FakeTransitionQuery $transitions;

    protected FakeSavedSearchStore $savedSearches;

    protected FakeAlertRuleStore $alertRules;

    protected function getPackageProviders($app): array
    {
        return [ArgusServiceProvider::class, ArgusApiServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // The core provider needs a declared store, but every storage contract is
        // replaced with a fake in setUp(), so nothing reaches Postgres or Redis.
        $app['config']->set('argus.store', 'postgres');
        $app['config']->set('argus.schedule.enabled', false);
        $app['config']->set('argus.alerting.enabled', false);

        // Default the API to no auth middleware so endpoint tests exercise the
        // controllers directly; auth-specific tests override this per test.
        $app['config']->set('argus-api.middleware', []);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->transitions = new FakeTransitionQuery;
        $this->savedSearches = new FakeSavedSearchStore;
        $this->alertRules = new FakeAlertRuleStore;

        $this->app->instance(TransitionQuery::class, $this->transitions);
        $this->app->instance(SavedSearchStore::class, $this->savedSearches);
        $this->app->instance(AlertRuleStore::class, $this->alertRules);
    }

    protected function actingAsUser(): static
    {
        return $this->actingAs(new GenericUser(['id' => 1, 'name' => 'Test User']));
    }
}
```

- [ ] **Step 5: Verify the harness boots**

Run: `./vendor/bin/phpunit --filter nothing-matches-this 2>&1 | tail -5`
Expected: "No tests executed" (or "OK (0 tests)"); critically, no fatal/bootstrap error. This proves both providers register and the fakes bind without Postgres/Redis.

- [ ] **Step 6: Commit**

```bash
git add tests/
git commit -m "test: add testbench harness with in-memory core fakes

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: Config and service-provider skeleton

**Files:**
- Create: `config/argus-api.php`
- Create: `src/ArgusApiServiceProvider.php`
- Test: `tests/Unit/ConfigTest.php`

- [ ] **Step 1: Write the failing test `tests/Unit/ConfigTest.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Unit;

use ArgusApi\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ConfigTest extends TestCase
{
    #[Test]
    public function it_merges_default_config(): void
    {
        $this->assertSame('argus-api', config('argus-api.prefix'));
        $this->assertSame(100, config('argus-api.pagination.default_limit'));
        $this->assertSame(500, config('argus-api.pagination.max_limit'));
        $this->assertTrue(config('argus-api.authorization.allow_by_default'));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `./vendor/bin/phpunit --filter ConfigTest`
Expected: FAIL — `ArgusApiServiceProvider` does not exist / config null.

- [ ] **Step 3: Write `config/argus-api.php`**

```php
<?php

declare(strict_types=1);

return [
    // URL prefix all Argus API routes mount under.
    'prefix' => 'argus-api',

    // The authentication seam. Authentication is the consuming app's job; this
    // package only declares the stack its routes sit behind. Defaults to
    // Sanctum's stateful guard. Replace with the app's own guard(s) as needed.
    'middleware' => ['auth:sanctum'],

    'pagination' => [
        'default_limit' => 100,
        'max_limit' => 500,
    ],

    'authorization' => [
        // Default verdict for every Argus gate the app has NOT overridden. true
        // means any authenticated user passes (authentication already proved the
        // user is valid). Set false to deny by default, or define the gates in
        // your own provider to apply real role checks.
        'allow_by_default' => true,
    ],
];
```

- [ ] **Step 4: Write `src/ArgusApiServiceProvider.php`** (routes/auth wired in later tasks)

```php
<?php

declare(strict_types=1);

namespace ArgusApi;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class ArgusApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/argus-api.php', 'argus-api');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/argus-api.php' => $this->app->configPath('argus-api.php'),
        ], 'argus-api-config');

        $this->loadRoutes();
    }

    private function loadRoutes(): void
    {
        Route::group([
            'prefix' => config('argus-api.prefix', 'argus-api'),
            'middleware' => config('argus-api.middleware', ['auth:sanctum']),
        ], function (): void {
            require __DIR__.'/../routes/argus-api.php';
        });
    }
}
```

- [ ] **Step 5: Create an empty routes file so `loadRoutes()` does not fatal**

Create `routes/argus-api.php`:

```php
<?php

declare(strict_types=1);

// Argus API routes. Controllers are added in Tasks 9-12.
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `./vendor/bin/phpunit --filter ConfigTest`
Expected: PASS (1 test).

- [ ] **Step 7: Format and commit**

```bash
./vendor/bin/pint --dirty
git add config/ src/ routes/ tests/Unit/ConfigTest.php
git commit -m "feat: config and service provider with route group seam

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: Response envelope, validation base, not-found exception

**Files:**
- Create: `src/Http/Support/ApiResponse.php`
- Create: `src/Exceptions/NotFoundException.php`
- Create: `src/Http/Requests/ApiFormRequest.php`
- Test: `tests/Unit/EnvelopeTest.php`

- [ ] **Step 1: Write the failing test `tests/Unit/EnvelopeTest.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Unit;

use ArgusApi\Exceptions\NotFoundException;
use ArgusApi\Http\Support\ApiResponse;
use ArgusApi\Tests\TestCase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;

final class EnvelopeTest extends TestCase
{
    #[Test]
    public function ok_wraps_data_and_meta(): void
    {
        $response = ApiResponse::ok(['a' => 1], ['total' => 5]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"data":{"a":1},"meta":{"total":5}}', $response->getContent());
    }

    #[Test]
    public function ok_serialises_empty_list_as_array_and_empty_meta_as_object(): void
    {
        $response = ApiResponse::ok([], []);

        $this->assertSame('{"data":[],"meta":{}}', $response->getContent());
    }

    #[Test]
    public function error_shape_is_type_message_details(): void
    {
        $response = ApiResponse::error('not_found', 'Missing', 404, ['id' => ['bad']]);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('{"error":{"type":"not_found","message":"Missing","details":{"id":["bad"]}}}', $response->getContent());
    }

    #[Test]
    public function not_found_exception_renders_404_envelope(): void
    {
        $response = (new NotFoundException('Unknown job [x].'))->render(Request::create('/'));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('{"error":{"type":"not_found","message":"Unknown job [x].","details":{}}}', $response->getContent());
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `./vendor/bin/phpunit --filter EnvelopeTest`
Expected: FAIL — classes do not exist.

- [ ] **Step 3: Write `src/Http/Support/ApiResponse.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Http\Support;

use Illuminate\Http\JsonResponse;

/**
 * Builds the single JSON envelope every Argus API endpoint returns. Success is
 * {"data": ..., "meta": {...}}; failure is {"error": {"type","message","details"}}.
 * Lists stay JSON arrays; meta and details are always JSON objects (an empty
 * array would otherwise serialise as []), which keeps the Phase 5 client's types
 * stable.
 */
final readonly class ApiResponse
{
    /**
     * @param  array<mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    public static function ok(array $data, array $meta = []): JsonResponse
    {
        return new JsonResponse(['data' => $data, 'meta' => (object) $meta], 200);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function created(array $data): JsonResponse
    {
        return new JsonResponse(['data' => $data, 'meta' => (object) []], 201);
    }

    public static function noContent(): JsonResponse
    {
        return new JsonResponse(null, 204);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function error(string $type, string $message, int $status, array $details = []): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'type' => $type,
                'message' => $message,
                'details' => (object) $details,
            ],
        ], $status);
    }
}
```

- [ ] **Step 4: Write `src/Exceptions/NotFoundException.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Exceptions;

use ArgusApi\Http\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when a referenced job_uuid, saved search, or alert rule does not exist.
 * Renders its own 404 envelope, so controllers need no try/catch and the package
 * never registers anything in the host app's exception handler.
 */
final class NotFoundException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error('not_found', $this->getMessage() ?: 'Resource not found.', 404);
    }
}
```

- [ ] **Step 5: Write `src/Http/Requests/ApiFormRequest.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Http\Requests;

use ArgusApi\Http\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base request that renders validation failures into the package's 422 envelope
 * instead of Laravel's default {message, errors} shape. Authorization is handled
 * by the controllers' gate checks, so authorize() is open here.
 */
abstract class ApiFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error('validation', 'The given data was invalid.', 422, $validator->errors()->toArray())
        );
    }
}
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `./vendor/bin/phpunit --filter EnvelopeTest`
Expected: PASS (4 tests).

- [ ] **Step 7: Format and commit**

```bash
./vendor/bin/pint --dirty
git add src/ tests/Unit/EnvelopeTest.php
git commit -m "feat: JSON envelope, validation base, not-found exception

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: Authorization gates and the gate guard

**Files:**
- Create: `src/Authorization/Abilities.php`
- Create: `src/Authorization/AuthorizationServiceProvider.php`
- Create: `src/Authorization/Authorize.php`
- Create: `src/Exceptions/ForbiddenException.php`
- Create: `stubs/argus-api-authorization-provider.stub`
- Modify: `src/ArgusApiServiceProvider.php` (register the authorization provider + publish the stub)
- Test: `tests/Unit/GatesTest.php`

- [ ] **Step 1: Write the failing test `tests/Unit/GatesTest.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Unit;

use ArgusApi\Authorization\Abilities;
use ArgusApi\Tests\TestCase;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Access\Gate;
use PHPUnit\Framework\Attributes\Test;

final class GatesTest extends TestCase
{
    #[Test]
    public function all_four_abilities_are_defined(): void
    {
        $gate = $this->app->make(Gate::class);

        foreach (Abilities::all() as $ability) {
            $this->assertTrue($gate->has($ability), "gate {$ability} not defined");
        }

        $this->assertSame(
            ['view-jobs', 'view-failures', 'manage-saved-searches', 'manage-alerts'],
            Abilities::all(),
        );
    }

    #[Test]
    public function gates_allow_authenticated_users_by_default(): void
    {
        $gate = $this->app->make(Gate::class)->forUser(new GenericUser(['id' => 1]));

        $this->assertTrue($gate->allows(Abilities::VIEW_JOBS));
        $this->assertTrue($gate->allows(Abilities::MANAGE_ALERTS));
    }

    #[Test]
    public function default_verdict_follows_config(): void
    {
        config()->set('argus-api.authorization.allow_by_default', false);
        $gate = $this->app->make(Gate::class)->forUser(new GenericUser(['id' => 1]));

        $this->assertFalse($gate->allows(Abilities::VIEW_JOBS));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `./vendor/bin/phpunit --filter GatesTest`
Expected: FAIL — `Abilities` does not exist.

- [ ] **Step 3: Write `src/Authorization/Abilities.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Authorization;

/**
 * The four authorization abilities this package guards its endpoints with. The
 * app may redefine any of these gates in its own provider to apply real role
 * checks; the names are the contract.
 */
final class Abilities
{
    public const VIEW_JOBS = 'view-jobs';

    public const VIEW_FAILURES = 'view-failures';

    public const MANAGE_SAVED_SEARCHES = 'manage-saved-searches';

    public const MANAGE_ALERTS = 'manage-alerts';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::VIEW_JOBS,
            self::VIEW_FAILURES,
            self::MANAGE_SAVED_SEARCHES,
            self::MANAGE_ALERTS,
        ];
    }
}
```

- [ ] **Step 4: Write `src/Authorization/AuthorizationServiceProvider.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Authorization;

use Illuminate\Contracts\Auth\Access\Authenticatable;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Registers a default gate for each Argus ability, but only when the host app
 * has not already defined one (Gate::has). Defaults return the configured
 * allow_by_default verdict (true out of the box: authentication already proved
 * the user is valid). The app tightens authorization either by flipping that
 * config flag or by defining the same-named gates in its own provider.
 */
final class AuthorizationServiceProvider extends ServiceProvider
{
    public function boot(Gate $gate): void
    {
        foreach (Abilities::all() as $ability) {
            if ($gate->has($ability)) {
                continue;
            }

            $gate->define(
                $ability,
                fn (?Authenticatable $user) => (bool) config('argus-api.authorization.allow_by_default', true),
            );
        }
    }
}
```

- [ ] **Step 5: Write `src/Exceptions/ForbiddenException.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Exceptions;

use ArgusApi\Http\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when an authenticated user fails an authorization gate. Renders its own
 * 403 envelope. (Unauthenticated requests never reach here: the app's auth
 * middleware rejects them before any controller runs.)
 */
final class ForbiddenException extends RuntimeException
{
    public function __construct(private readonly string $ability)
    {
        parent::__construct("You are not authorized to perform [{$ability}].");
    }

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error('forbidden', $this->getMessage(), 403, ['ability' => $this->ability]);
    }
}
```

- [ ] **Step 6: Write `src/Authorization/Authorize.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Authorization;

use ArgusApi\Exceptions\ForbiddenException;
use Illuminate\Contracts\Auth\Access\Authenticatable;
use Illuminate\Contracts\Auth\Access\Gate;

/**
 * One place every controller funnels its gate check through, so the 403 path is
 * identical everywhere. Throws ForbiddenException (which renders the envelope)
 * when the user is denied.
 */
final readonly class Authorize
{
    public static function check(Gate $gate, ?Authenticatable $user, string $ability): void
    {
        if ($gate->forUser($user)->denies($ability)) {
            throw new ForbiddenException($ability);
        }
    }
}
```

- [ ] **Step 7: Write `stubs/argus-api-authorization-provider.stub`** (publishable; lets an app define explicit gates)

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use ArgusApi\Authorization\Abilities;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Published from acme/queue-observability-api. Register this in bootstrap/providers.php
 * and edit each gate to match your app's roles. Because these definitions load,
 * the package's permissive defaults are not used for these abilities.
 */
final class ArgusApiAuthorizationServiceProvider extends ServiceProvider
{
    public function boot(Gate $gate): void
    {
        $gate->define(Abilities::VIEW_JOBS, fn ($user) => true);
        $gate->define(Abilities::VIEW_FAILURES, fn ($user) => true);
        $gate->define(Abilities::MANAGE_SAVED_SEARCHES, fn ($user) => true);
        $gate->define(Abilities::MANAGE_ALERTS, fn ($user) => true);
    }
}
```

- [ ] **Step 8: Modify `src/ArgusApiServiceProvider.php`** to register the authorization provider and publish the stub

Replace the `register()` and `boot()` methods with:

```php
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/argus-api.php', 'argus-api');

        $this->app->register(\ArgusApi\Authorization\AuthorizationServiceProvider::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/argus-api.php' => $this->app->configPath('argus-api.php'),
        ], 'argus-api-config');

        $this->publishes([
            __DIR__.'/../stubs/argus-api-authorization-provider.stub' => $this->app->basePath('app/Providers/ArgusApiAuthorizationServiceProvider.php'),
        ], 'argus-api-authorization');

        $this->loadRoutes();
    }
```

- [ ] **Step 9: Run the test to verify it passes**

Run: `./vendor/bin/phpunit --filter GatesTest`
Expected: PASS (3 tests).

- [ ] **Step 10: Format and commit**

```bash
./vendor/bin/pint --dirty
git add src/ stubs/ tests/Unit/GatesTest.php
git commit -m "feat: publishable authorization gates with allow-authenticated defaults

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 6: Filter mapping (JSON to JobFilter) and filter validation rules

**Files:**
- Create: `src/Http/Requests/FilterRules.php`
- Create: `src/Http/Support/FilterInput.php`
- Create: `src/Http/Requests/SearchRequest.php`
- Create: `src/Http/Requests/FailureRequest.php`
- Test: `tests/Unit/FilterInputTest.php`

- [ ] **Step 1: Write the failing test `tests/Unit/FilterInputTest.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Unit;

use Argus\Support\TransitionType;
use ArgusApi\Http\Support\FilterInput;
use ArgusApi\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class FilterInputTest extends TestCase
{
    #[Test]
    public function it_maps_json_to_a_job_filter_via_the_core_codec(): void
    {
        $filter = $this->app->make(FilterInput::class)->fromValidated([
            'queue' => 'emails',
            'status' => 'failed',
            'since' => '2026-05-01T00:00:00+00:00',
            'limit' => 25,
            'offset' => 50,
        ]);

        $this->assertSame('emails', $filter->queue);
        $this->assertSame(TransitionType::FAILED, $filter->status);
        $this->assertSame('2026-05-01T00:00:00+00:00', $filter->since?->toIso8601String());
        $this->assertSame(25, $filter->limit);
        $this->assertSame(50, $filter->offset);
        $this->assertNull($filter->jobClass);
    }

    #[Test]
    public function it_applies_the_default_limit_when_absent(): void
    {
        $filter = $this->app->make(FilterInput::class)->fromValidated([]);

        $this->assertSame(100, $filter->limit);
        $this->assertSame(0, $filter->offset);
    }

    #[Test]
    public function it_clamps_the_limit_to_the_configured_maximum(): void
    {
        config()->set('argus-api.pagination.max_limit', 200);

        $filter = $this->app->make(FilterInput::class)->fromValidated(['limit' => 9999]);

        $this->assertSame(200, $filter->limit);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `./vendor/bin/phpunit --filter FilterInputTest`
Expected: FAIL — `FilterInput` does not exist.

- [ ] **Step 3: Write `src/Http/Support/FilterInput.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Http\Support;

use Argus\Query\FilterCodec;
use Argus\Query\JobFilter;

/**
 * Turns a validated JSON filter body into the core's JobFilter. It reuses the
 * core's FilterCodec verbatim (the one and only filter representation), then
 * applies this package's pagination policy: a default limit when the client
 * omits one and a hard clamp to the configured maximum so a request cannot ask
 * for an unbounded page.
 */
final readonly class FilterInput
{
    public function __construct(private FilterCodec $codec) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function fromValidated(array $data): JobFilter
    {
        $default = (int) config('argus-api.pagination.default_limit', 100);
        $max = (int) config('argus-api.pagination.max_limit', 500);

        $limit = isset($data['limit']) ? (int) $data['limit'] : $default;
        $data['limit'] = min($limit, $max);
        $data['offset'] = isset($data['offset']) ? (int) $data['offset'] : 0;

        return $this->codec->decode($data);
    }
}
```

- [ ] **Step 4: Write `src/Http/Requests/FilterRules.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Http\Requests;

use Argus\Support\TransitionType;
use Illuminate\Validation\Rule;

/**
 * The validation rules for the filter object, shared by the search and failure
 * endpoints (top-level) and the saved-search endpoints (nested under "filter").
 * Field names match the core FilterCodec keys exactly.
 */
final class FilterRules
{
    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'jobClass' => ['nullable', 'string'],
            'queue' => ['nullable', 'string'],
            'tenantId' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(array_map(fn (TransitionType $t) => $t->value, TransitionType::cases()))],
            'attemptMin' => ['nullable', 'integer', 'min:0'],
            'attemptMax' => ['nullable', 'integer', 'min:0'],
            'since' => ['nullable', 'date'],
            'until' => ['nullable', 'date'],
            'correlationKey' => ['nullable', 'string', 'required_with:correlationValue'],
            'correlationValue' => ['nullable', 'string', 'required_with:correlationKey'],
            'limit' => ['nullable', 'integer', 'min:0'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * The same rules keyed under a prefix, for the nested "filter" object in
     * saved-search request bodies.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function prefixed(string $prefix): array
    {
        $out = [];
        foreach (self::rules() as $key => $rule) {
            $out["{$prefix}.{$key}"] = $rule;
        }

        return $out;
    }
}
```

- [ ] **Step 5: Write `src/Http/Requests/SearchRequest.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Http\Requests;

final class SearchRequest extends ApiFormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return FilterRules::rules();
    }
}
```

- [ ] **Step 6: Write `src/Http/Requests/FailureRequest.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Http\Requests;

final class FailureRequest extends ApiFormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return FilterRules::rules();
    }
}
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `./vendor/bin/phpunit --filter FilterInputTest`
Expected: PASS (3 tests).

- [ ] **Step 8: Format and commit**

```bash
./vendor/bin/pint --dirty
git add src/ tests/Unit/FilterInputTest.php
git commit -m "feat: filter input mapping and validation rules reusing core codec

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 7: DTO resources (presenters)

**Files:**
- Create: `src/Http/Resources/JobSummaryResource.php`
- Create: `src/Http/Resources/TransitionRecordResource.php`
- Create: `src/Http/Resources/FailureGroupResource.php`
- Create: `src/Http/Resources/SavedSearchResource.php`
- Create: `src/Http/Resources/AlertRuleResource.php`
- Test: `tests/Unit/ResourcesTest.php`

- [ ] **Step 1: Write the failing test `tests/Unit/ResourcesTest.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Unit;

use Argus\Alerting\AlertRule;
use Argus\Alerting\AlertState;
use Argus\Query\FailureGroup;
use Argus\Query\JobFilter;
use Argus\Query\JobSummary;
use Argus\Query\TransitionRecord;
use Argus\SavedSearches\SavedSearch;
use Argus\Support\TransitionType;
use ArgusApi\Http\Resources\AlertRuleResource;
use ArgusApi\Http\Resources\FailureGroupResource;
use ArgusApi\Http\Resources\JobSummaryResource;
use ArgusApi\Http\Resources\SavedSearchResource;
use ArgusApi\Http\Resources\TransitionRecordResource;
use ArgusApi\Tests\TestCase;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;

final class ResourcesTest extends TestCase
{
    #[Test]
    public function job_summary_includes_derived_in_flight(): void
    {
        $dispatched = CarbonImmutable::parse('2026-05-01T10:00:00+00:00');
        $summary = new JobSummary('uuid-1', 'App\\Jobs\\Send', 'emails', 'acme', 'processing', 2, $dispatched, null, null, null);

        $this->assertSame([
            'jobUuid' => 'uuid-1',
            'jobClass' => 'App\\Jobs\\Send',
            'queue' => 'emails',
            'tenantId' => 'acme',
            'status' => 'processing',
            'attempts' => 2,
            'dispatchedAt' => '2026-05-01T10:00:00+00:00',
            'finishedAt' => null,
            'durationMs' => null,
            'exceptionFingerprint' => null,
            'inFlight' => true,
        ], JobSummaryResource::toArray($summary));
    }

    #[Test]
    public function transition_record_renders_enum_as_value(): void
    {
        $at = CarbonImmutable::parse('2026-05-01T10:00:00+00:00');
        $record = new TransitionRecord('uuid-1', 3, TransitionType::FAILED, 2, $at, 120, 'fp-9', 'boom');

        $array = TransitionRecordResource::toArray($record);

        $this->assertSame('failed', $array['transition']);
        $this->assertSame(3, $array['sequence']);
        $this->assertSame('2026-05-01T10:00:00+00:00', $array['occurredAt']);
        $this->assertSame('boom', $array['exceptionMessage']);
    }

    #[Test]
    public function failure_group_renders_counts_and_window(): void
    {
        $first = CarbonImmutable::parse('2026-05-01T10:00:00+00:00');
        $last = CarbonImmutable::parse('2026-05-01T12:00:00+00:00');
        $group = new FailureGroup('fp-9', 'boom', 7, $first, $last);

        $this->assertSame([
            'fingerprint' => 'fp-9',
            'representativeMessage' => 'boom',
            'count' => 7,
            'firstSeen' => '2026-05-01T10:00:00+00:00',
            'lastSeen' => '2026-05-01T12:00:00+00:00',
        ], FailureGroupResource::toArray($group));
    }

    #[Test]
    public function saved_search_embeds_the_encoded_filter(): void
    {
        $now = CarbonImmutable::parse('2026-05-01T10:00:00+00:00');
        $saved = new SavedSearch('7', 'Failed emails', new JobFilter(queue: 'emails', status: TransitionType::FAILED), $now, $now);

        $array = SavedSearchResource::toArray($saved);

        $this->assertSame('7', $array['id']);
        $this->assertSame('Failed emails', $array['name']);
        $this->assertSame('emails', $array['filter']['queue']);
        $this->assertSame('failed', $array['filter']['status']);
        $this->assertSame('2026-05-01T10:00:00+00:00', $array['createdAt']);
    }

    #[Test]
    public function alert_rule_renders_state_and_sinks(): void
    {
        $now = CarbonImmutable::parse('2026-05-01T10:00:00+00:00');
        $rule = new AlertRule('5', '7', 'High failures', 10, 300, 600, ['slack'], true, AlertState::OK, null, null, null, $now, $now);

        $array = AlertRuleResource::toArray($rule);

        $this->assertSame('5', $array['id']);
        $this->assertSame('7', $array['savedSearchId']);
        $this->assertSame(10, $array['threshold']);
        $this->assertSame(['slack'], $array['sinks']);
        $this->assertTrue($array['enabled']);
        $this->assertSame('ok', $array['state']);
        $this->assertNull($array['lastNotifiedAt']);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `./vendor/bin/phpunit --filter ResourcesTest`
Expected: FAIL — resource classes do not exist.

- [ ] **Step 3: Write `src/Http/Resources/JobSummaryResource.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Http\Resources;

use Argus\Query\JobSummary;

final readonly class JobSummaryResource
{
    /** @return array<string, mixed> */
    public static function toArray(JobSummary $summary): array
    {
        return [
            'jobUuid' => $summary->jobUuid,
            'jobClass' => $summary->jobClass,
            'queue' => $summary->queue,
            'tenantId' => $summary->tenantId,
            'status' => $summary->status,
            'attempts' => $summary->attempts,
            'dispatchedAt' => $summary->dispatchedAt?->toIso8601String(),
            'finishedAt' => $summary->finishedAt?->toIso8601String(),
            'durationMs' => $summary->durationMs,
            'exceptionFingerprint' => $summary->exceptionFingerprint,
            'inFlight' => $summary->isInFlight(),
        ];
    }

    /**
     * @param  list<JobSummary>  $summaries
     * @return list<array<string, mixed>>
     */
    public static function collection(array $summaries): array
    {
        return array_map(self::toArray(...), $summaries);
    }
}
```

- [ ] **Step 4: Write `src/Http/Resources/TransitionRecordResource.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Http\Resources;

use Argus\Query\TransitionRecord;

final readonly class TransitionRecordResource
{
    /** @return array<string, mixed> */
    public static function toArray(TransitionRecord $record): array
    {
        return [
            'jobUuid' => $record->jobUuid,
            'sequence' => $record->sequence,
            'transition' => $record->transition->value,
            'attempt' => $record->attempt,
            'occurredAt' => $record->occurredAt->toIso8601String(),
            'durationMs' => $record->durationMs,
            'exceptionFingerprint' => $record->exceptionFingerprint,
            'exceptionMessage' => $record->exceptionMessage,
        ];
    }

    /**
     * @param  list<TransitionRecord>  $records
     * @return list<array<string, mixed>>
     */
    public static function collection(array $records): array
    {
        return array_map(self::toArray(...), $records);
    }
}
```

- [ ] **Step 5: Write `src/Http/Resources/FailureGroupResource.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Http\Resources;

use Argus\Query\FailureGroup;

final readonly class FailureGroupResource
{
    /** @return array<string, mixed> */
    public static function toArray(FailureGroup $group): array
    {
        return [
            'fingerprint' => $group->fingerprint,
            'representativeMessage' => $group->representativeMessage,
            'count' => $group->count,
            'firstSeen' => $group->firstSeen->toIso8601String(),
            'lastSeen' => $group->lastSeen->toIso8601String(),
        ];
    }

    /**
     * @param  list<FailureGroup>  $groups
     * @return list<array<string, mixed>>
     */
    public static function collection(array $groups): array
    {
        return array_map(self::toArray(...), $groups);
    }
}
```

- [ ] **Step 6: Write `src/Http/Resources/SavedSearchResource.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Http\Resources;

use Argus\Query\FilterCodec;
use Argus\SavedSearches\SavedSearch;

final readonly class SavedSearchResource
{
    /** @return array<string, mixed> */
    public static function toArray(SavedSearch $saved): array
    {
        return [
            'id' => $saved->id,
            'name' => $saved->name,
            'filter' => (new FilterCodec)->encode($saved->filter),
            'createdAt' => $saved->createdAt->toIso8601String(),
            'updatedAt' => $saved->updatedAt->toIso8601String(),
        ];
    }

    /**
     * @param  list<SavedSearch>  $items
     * @return list<array<string, mixed>>
     */
    public static function collection(array $items): array
    {
        return array_map(self::toArray(...), $items);
    }
}
```

- [ ] **Step 7: Write `src/Http/Resources/AlertRuleResource.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Http\Resources;

use Argus\Alerting\AlertRule;

final readonly class AlertRuleResource
{
    /** @return array<string, mixed> */
    public static function toArray(AlertRule $rule): array
    {
        return [
            'id' => $rule->id,
            'savedSearchId' => $rule->savedSearchId,
            'name' => $rule->name,
            'threshold' => $rule->threshold,
            'windowSeconds' => $rule->windowSeconds,
            'cooldownSeconds' => $rule->cooldownSeconds,
            'sinks' => $rule->sinks,
            'enabled' => $rule->enabled,
            'state' => $rule->state->value,
            'lastNotifiedAt' => $rule->lastNotifiedAt?->toIso8601String(),
            'lastResultCount' => $rule->lastResultCount,
            'lastEvaluatedAt' => $rule->lastEvaluatedAt?->toIso8601String(),
            'createdAt' => $rule->createdAt->toIso8601String(),
            'updatedAt' => $rule->updatedAt->toIso8601String(),
        ];
    }

    /**
     * @param  list<AlertRule>  $rules
     * @return list<array<string, mixed>>
     */
    public static function collection(array $rules): array
    {
        return array_map(self::toArray(...), $rules);
    }
}
```

- [ ] **Step 8: Run the test to verify it passes**

Run: `./vendor/bin/phpunit --filter ResourcesTest`
Expected: PASS (5 tests).

- [ ] **Step 9: Format and commit**

```bash
./vendor/bin/pint --dirty
git add src/ tests/Unit/ResourcesTest.php
git commit -m "feat: DTO resource presenters for all API shapes

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 8: Search endpoint

**Files:**
- Create: `src/Http/Controllers/SearchController.php`
- Modify: `routes/argus-api.php`
- Test: `tests/Feature/SearchEndpointTest.php`

- [ ] **Step 1: Write the failing test `tests/Feature/SearchEndpointTest.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Feature;

use Argus\Query\JobSummary;
use ArgusApi\Tests\TestCase;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;

final class SearchEndpointTest extends TestCase
{
    private function summary(string $uuid, string $queue, string $status, ?CarbonImmutable $dispatched = null): JobSummary
    {
        return new JobSummary($uuid, 'App\\Jobs\\Send', $queue, 'acme', $status, 1, $dispatched ?? CarbonImmutable::parse('2026-05-01T10:00:00+00:00'), null, null, null);
    }

    #[Test]
    public function it_returns_matching_jobs_in_the_envelope(): void
    {
        $this->transitions->summaries = [
            $this->summary('uuid-1', 'emails', 'failed'),
            $this->summary('uuid-2', 'sms', 'failed'),
        ];

        $response = $this->actingAsUser()->postJson('argus-api/search', ['queue' => 'emails']);

        $response->assertOk()
            ->assertJsonPath('data.0.jobUuid', 'uuid-1')
            ->assertJsonPath('data.0.inFlight', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.limit', 100)
            ->assertJsonPath('meta.offset', 0);
    }

    #[Test]
    public function it_paginates_with_total_reflecting_the_full_match_set(): void
    {
        $this->transitions->summaries = [
            $this->summary('uuid-1', 'emails', 'failed', CarbonImmutable::parse('2026-05-01T10:00:00+00:00')),
            $this->summary('uuid-2', 'emails', 'failed', CarbonImmutable::parse('2026-05-01T09:00:00+00:00')),
            $this->summary('uuid-3', 'emails', 'failed', CarbonImmutable::parse('2026-05-01T08:00:00+00:00')),
        ];

        $response = $this->actingAsUser()->postJson('argus-api/search', ['queue' => 'emails', 'limit' => 2, 'offset' => 0]);

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.jobUuid', 'uuid-1')
            ->assertJsonPath('data.1.jobUuid', 'uuid-2')
            ->assertJsonPath('meta.total', 3);
    }

    #[Test]
    public function an_empty_result_set_is_an_empty_list_not_an_error(): void
    {
        $response = $this->actingAsUser()->postJson('argus-api/search', ['queue' => 'nope']);

        $response->assertOk()
            ->assertExactJson(['data' => [], 'meta' => ['total' => 0, 'limit' => 100, 'offset' => 0]]);
    }

    #[Test]
    public function an_invalid_status_is_a_422_validation_envelope(): void
    {
        $response = $this->actingAsUser()->postJson('argus-api/search', ['status' => 'not-a-status']);

        $response->assertStatus(422)
            ->assertJsonPath('error.type', 'validation')
            ->assertJsonStructure(['error' => ['type', 'message', 'details' => ['status']]]);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `./vendor/bin/phpunit --filter SearchEndpointTest`
Expected: FAIL — route not found (404) / `SearchController` missing.

- [ ] **Step 3: Write `src/Http/Controllers/SearchController.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers;

use Argus\Query\JobQueryService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Http\Requests\SearchRequest;
use ArgusApi\Http\Resources\JobSummaryResource;
use ArgusApi\Http\Support\ApiResponse;
use ArgusApi\Http\Support\FilterInput;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;

final readonly class SearchController
{
    public function __construct(
        private JobQueryService $query,
        private FilterInput $filter,
        private Gate $gate,
    ) {}

    public function __invoke(SearchRequest $request): JsonResponse
    {
        Authorize::check($this->gate, $request->user(), Abilities::VIEW_JOBS);

        $filter = $this->filter->fromValidated($request->validated());

        return ApiResponse::ok(
            JobSummaryResource::collection($this->query->search($filter)),
            [
                'total' => $this->query->count($filter),
                'limit' => $filter->limit,
                'offset' => $filter->offset,
            ],
        );
    }
}
```

- [ ] **Step 4: Replace `routes/argus-api.php` with the search route**

```php
<?php

declare(strict_types=1);

use ArgusApi\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::post('search', SearchController::class)->name('argus-api.search');
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `./vendor/bin/phpunit --filter SearchEndpointTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Format and commit**

```bash
./vendor/bin/pint --dirty
git add src/ routes/ tests/Feature/SearchEndpointTest.php
git commit -m "feat: POST /search endpoint with pagination envelope

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 9: Job-history and failure-grouping endpoints

**Files:**
- Create: `src/Http/Controllers/JobHistoryController.php`
- Create: `src/Http/Controllers/FailureGroupController.php`
- Modify: `routes/argus-api.php`
- Test: `tests/Feature/JobHistoryEndpointTest.php`
- Test: `tests/Feature/FailureGroupEndpointTest.php`

- [ ] **Step 1: Write the failing test `tests/Feature/JobHistoryEndpointTest.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Feature;

use Argus\Query\TransitionRecord;
use Argus\Support\TransitionType;
use ArgusApi\Tests\TestCase;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;

final class JobHistoryEndpointTest extends TestCase
{
    #[Test]
    public function it_returns_the_ordered_timeline_for_a_known_job(): void
    {
        $at = CarbonImmutable::parse('2026-05-01T10:00:00+00:00');
        $this->transitions->histories['uuid-1'] = [
            new TransitionRecord('uuid-1', 1, TransitionType::QUEUED, 1, $at, null, null, null),
            new TransitionRecord('uuid-1', 2, TransitionType::FAILED, 1, $at, 50, 'fp-1', 'boom'),
        ];

        $response = $this->actingAsUser()->getJson('argus-api/jobs/uuid-1/history');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.transition', 'queued')
            ->assertJsonPath('data.1.transition', 'failed')
            ->assertJsonPath('meta.jobUuid', 'uuid-1')
            ->assertJsonPath('meta.count', 2);
    }

    #[Test]
    public function an_unknown_job_uuid_is_a_404(): void
    {
        $response = $this->actingAsUser()->getJson('argus-api/jobs/does-not-exist/history');

        $response->assertStatus(404)
            ->assertJsonPath('error.type', 'not_found');
    }
}
```

- [ ] **Step 2: Write the failing test `tests/Feature/FailureGroupEndpointTest.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Feature;

use Argus\Query\FailureGroup;
use ArgusApi\Tests\TestCase;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;

final class FailureGroupEndpointTest extends TestCase
{
    #[Test]
    public function it_returns_failure_groups_in_the_envelope(): void
    {
        $first = CarbonImmutable::parse('2026-05-01T10:00:00+00:00');
        $last = CarbonImmutable::parse('2026-05-01T12:00:00+00:00');
        $this->transitions->failureGroups = [
            new FailureGroup('fp-1', 'boom', 9, $first, $last),
        ];

        $response = $this->actingAsUser()->postJson('argus-api/failures', ['queue' => 'emails']);

        $response->assertOk()
            ->assertJsonPath('data.0.fingerprint', 'fp-1')
            ->assertJsonPath('data.0.count', 9)
            ->assertJsonPath('meta.count', 1);
    }

    #[Test]
    public function no_failures_is_an_empty_list(): void
    {
        $response = $this->actingAsUser()->postJson('argus-api/failures', ['queue' => 'emails']);

        $response->assertOk()->assertExactJson(['data' => [], 'meta' => ['count' => 0]]);
    }
}
```

- [ ] **Step 3: Run them to verify they fail**

Run: `./vendor/bin/phpunit --filter "JobHistoryEndpointTest|FailureGroupEndpointTest"`
Expected: FAIL — routes/controllers missing.

- [ ] **Step 4: Write `src/Http/Controllers/JobHistoryController.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers;

use Argus\Query\JobQueryService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Exceptions\NotFoundException;
use ArgusApi\Http\Resources\TransitionRecordResource;
use ArgusApi\Http\Support\ApiResponse;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class JobHistoryController
{
    public function __construct(
        private JobQueryService $query,
        private Gate $gate,
    ) {}

    public function __invoke(Request $request, string $jobUuid): JsonResponse
    {
        Authorize::check($this->gate, $request->user(), Abilities::VIEW_JOBS);

        $history = $this->query->getHistory($jobUuid);

        // Every recorded job has at least a QUEUED transition, so an empty
        // history means the uuid was never recorded: that is a 404, not [].
        if ($history === []) {
            throw new NotFoundException("Unknown job [{$jobUuid}].");
        }

        return ApiResponse::ok(
            TransitionRecordResource::collection($history),
            ['jobUuid' => $jobUuid, 'count' => count($history)],
        );
    }
}
```

- [ ] **Step 5: Write `src/Http/Controllers/FailureGroupController.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers;

use Argus\Query\JobQueryService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Http\Requests\FailureRequest;
use ArgusApi\Http\Resources\FailureGroupResource;
use ArgusApi\Http\Support\ApiResponse;
use ArgusApi\Http\Support\FilterInput;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;

final readonly class FailureGroupController
{
    public function __construct(
        private JobQueryService $query,
        private FilterInput $filter,
        private Gate $gate,
    ) {}

    public function __invoke(FailureRequest $request): JsonResponse
    {
        Authorize::check($this->gate, $request->user(), Abilities::VIEW_FAILURES);

        $groups = $this->query->groupFailures($this->filter->fromValidated($request->validated()));

        return ApiResponse::ok(
            FailureGroupResource::collection($groups),
            ['count' => count($groups)],
        );
    }
}
```

- [ ] **Step 6: Append the two routes to `routes/argus-api.php`**

Add the imports at the top and the routes after the search route, so the file reads:

```php
<?php

declare(strict_types=1);

use ArgusApi\Http\Controllers\FailureGroupController;
use ArgusApi\Http\Controllers\JobHistoryController;
use ArgusApi\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::post('search', SearchController::class)->name('argus-api.search');
Route::get('jobs/{jobUuid}/history', JobHistoryController::class)->name('argus-api.jobs.history');
Route::post('failures', FailureGroupController::class)->name('argus-api.failures');
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `./vendor/bin/phpunit --filter "JobHistoryEndpointTest|FailureGroupEndpointTest"`
Expected: PASS (4 tests).

- [ ] **Step 8: Format and commit**

```bash
./vendor/bin/pint --dirty
git add src/ routes/ tests/Feature/JobHistoryEndpointTest.php tests/Feature/FailureGroupEndpointTest.php
git commit -m "feat: job-history and failure-grouping endpoints

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 10: Saved-search CRUD and results

**Files:**
- Create: `src/Http/Requests/SaveSearchRequest.php`
- Create: `src/Http/Controllers/SavedSearches/ListSavedSearchesController.php`
- Create: `src/Http/Controllers/SavedSearches/CreateSavedSearchController.php`
- Create: `src/Http/Controllers/SavedSearches/ShowSavedSearchController.php`
- Create: `src/Http/Controllers/SavedSearches/UpdateSavedSearchController.php`
- Create: `src/Http/Controllers/SavedSearches/DeleteSavedSearchController.php`
- Create: `src/Http/Controllers/SavedSearches/SavedSearchResultsController.php`
- Modify: `routes/argus-api.php`
- Test: `tests/Feature/SavedSearchEndpointTest.php`

- [ ] **Step 1: Write the failing test `tests/Feature/SavedSearchEndpointTest.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Feature;

use Argus\Query\JobSummary;
use ArgusApi\Tests\TestCase;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;

final class SavedSearchEndpointTest extends TestCase
{
    /** @return array{0:string} the created id */
    private function createOne(string $name = 'Failed emails'): array
    {
        $response = $this->actingAsUser()->postJson('argus-api/saved-searches', [
            'name' => $name,
            'filter' => ['queue' => 'emails', 'status' => 'failed'],
        ]);

        return [(string) $response->json('data.id')];
    }

    #[Test]
    public function it_creates_a_saved_search_and_echoes_the_filter(): void
    {
        $response = $this->actingAsUser()->postJson('argus-api/saved-searches', [
            'name' => 'Failed emails',
            'filter' => ['queue' => 'emails', 'status' => 'failed'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Failed emails')
            ->assertJsonPath('data.filter.queue', 'emails')
            ->assertJsonPath('data.filter.status', 'failed');
    }

    #[Test]
    public function it_lists_saved_searches(): void
    {
        $this->createOne('A');
        $this->createOne('B');

        $response = $this->actingAsUser()->getJson('argus-api/saved-searches');

        $response->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.count', 2);
    }

    #[Test]
    public function it_shows_a_saved_search(): void
    {
        [$id] = $this->createOne();

        $this->actingAsUser()->getJson("argus-api/saved-searches/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id);
    }

    #[Test]
    public function it_updates_a_saved_search(): void
    {
        [$id] = $this->createOne();

        $this->actingAsUser()->putJson("argus-api/saved-searches/{$id}", [
            'name' => 'Renamed',
            'filter' => ['queue' => 'sms'],
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.filter.queue', 'sms');
    }

    #[Test]
    public function it_deletes_a_saved_search(): void
    {
        [$id] = $this->createOne();

        $this->actingAsUser()->deleteJson("argus-api/saved-searches/{$id}")->assertNoContent();
        $this->actingAsUser()->getJson("argus-api/saved-searches/{$id}")->assertStatus(404);
    }

    #[Test]
    public function it_runs_a_saved_search_and_returns_results(): void
    {
        $this->transitions->summaries = [
            new JobSummary('uuid-1', 'App\\Jobs\\Send', 'emails', 'acme', 'failed', 1, CarbonImmutable::parse('2026-05-01T10:00:00+00:00'), null, null, null),
        ];
        [$id] = $this->createOne();

        $this->actingAsUser()->getJson("argus-api/saved-searches/{$id}/results")
            ->assertOk()
            ->assertJsonPath('data.0.jobUuid', 'uuid-1')
            ->assertJsonPath('meta.savedSearchId', $id);
    }

    #[Test]
    public function unknown_ids_are_404(): void
    {
        $this->actingAsUser()->getJson('argus-api/saved-searches/999')->assertStatus(404);
        $this->actingAsUser()->putJson('argus-api/saved-searches/999', ['name' => 'x', 'filter' => []])->assertStatus(404);
        $this->actingAsUser()->deleteJson('argus-api/saved-searches/999')->assertStatus(404);
        $this->actingAsUser()->getJson('argus-api/saved-searches/999/results')->assertStatus(404);
    }

    #[Test]
    public function create_requires_a_name(): void
    {
        $this->actingAsUser()->postJson('argus-api/saved-searches', ['filter' => []])
            ->assertStatus(422)
            ->assertJsonPath('error.type', 'validation');
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `./vendor/bin/phpunit --filter SavedSearchEndpointTest`
Expected: FAIL — routes/controllers missing.

- [ ] **Step 3: Write `src/Http/Requests/SaveSearchRequest.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Http\Requests;

final class SaveSearchRequest extends ApiFormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return array_merge([
            'name' => ['required', 'string', 'max:255'],
            'filter' => ['required', 'array'],
        ], FilterRules::prefixed('filter'));
    }
}
```

- [ ] **Step 4: Write `src/Http/Controllers/SavedSearches/ListSavedSearchesController.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers\SavedSearches;

use Argus\SavedSearches\SavedSearchService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Http\Resources\SavedSearchResource;
use ArgusApi\Http\Support\ApiResponse;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ListSavedSearchesController
{
    public function __construct(
        private SavedSearchService $service,
        private Gate $gate,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        Authorize::check($this->gate, $request->user(), Abilities::MANAGE_SAVED_SEARCHES);

        $all = $this->service->all();

        return ApiResponse::ok(SavedSearchResource::collection($all), ['count' => count($all)]);
    }
}
```

- [ ] **Step 5: Write `src/Http/Controllers/SavedSearches/CreateSavedSearchController.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers\SavedSearches;

use Argus\SavedSearches\SavedSearchService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Http\Requests\SaveSearchRequest;
use ArgusApi\Http\Resources\SavedSearchResource;
use ArgusApi\Http\Support\ApiResponse;
use ArgusApi\Http\Support\FilterInput;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;

final readonly class CreateSavedSearchController
{
    public function __construct(
        private SavedSearchService $service,
        private FilterInput $filter,
        private Gate $gate,
    ) {}

    public function __invoke(SaveSearchRequest $request): JsonResponse
    {
        Authorize::check($this->gate, $request->user(), Abilities::MANAGE_SAVED_SEARCHES);

        $validated = $request->validated();
        $saved = $this->service->create($validated['name'], $this->filter->fromValidated($validated['filter']));

        return ApiResponse::created(SavedSearchResource::toArray($saved));
    }
}
```

- [ ] **Step 6: Write `src/Http/Controllers/SavedSearches/ShowSavedSearchController.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers\SavedSearches;

use Argus\SavedSearches\SavedSearchService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Exceptions\NotFoundException;
use ArgusApi\Http\Resources\SavedSearchResource;
use ArgusApi\Http\Support\ApiResponse;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ShowSavedSearchController
{
    public function __construct(
        private SavedSearchService $service,
        private Gate $gate,
    ) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        Authorize::check($this->gate, $request->user(), Abilities::MANAGE_SAVED_SEARCHES);

        $saved = $this->service->find($id);
        if ($saved === null) {
            throw new NotFoundException("Unknown saved search [{$id}].");
        }

        return ApiResponse::ok(SavedSearchResource::toArray($saved));
    }
}
```

- [ ] **Step 7: Write `src/Http/Controllers/SavedSearches/UpdateSavedSearchController.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers\SavedSearches;

use Argus\SavedSearches\SavedSearchService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Exceptions\NotFoundException;
use ArgusApi\Http\Requests\SaveSearchRequest;
use ArgusApi\Http\Resources\SavedSearchResource;
use ArgusApi\Http\Support\ApiResponse;
use ArgusApi\Http\Support\FilterInput;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;

final readonly class UpdateSavedSearchController
{
    public function __construct(
        private SavedSearchService $service,
        private FilterInput $filter,
        private Gate $gate,
    ) {}

    public function __invoke(SaveSearchRequest $request, string $id): JsonResponse
    {
        Authorize::check($this->gate, $request->user(), Abilities::MANAGE_SAVED_SEARCHES);

        if ($this->service->find($id) === null) {
            throw new NotFoundException("Unknown saved search [{$id}].");
        }

        $validated = $request->validated();
        $saved = $this->service->update($id, $validated['name'], $this->filter->fromValidated($validated['filter']));

        return ApiResponse::ok(SavedSearchResource::toArray($saved));
    }
}
```

- [ ] **Step 8: Write `src/Http/Controllers/SavedSearches/DeleteSavedSearchController.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers\SavedSearches;

use Argus\SavedSearches\SavedSearchService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Exceptions\NotFoundException;
use ArgusApi\Http\Support\ApiResponse;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class DeleteSavedSearchController
{
    public function __construct(
        private SavedSearchService $service,
        private Gate $gate,
    ) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        Authorize::check($this->gate, $request->user(), Abilities::MANAGE_SAVED_SEARCHES);

        if ($this->service->find($id) === null) {
            throw new NotFoundException("Unknown saved search [{$id}].");
        }

        $this->service->delete($id);

        return ApiResponse::noContent();
    }
}
```

- [ ] **Step 9: Write `src/Http/Controllers/SavedSearches/SavedSearchResultsController.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers\SavedSearches;

use Argus\SavedSearches\SavedSearchService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Exceptions\NotFoundException;
use ArgusApi\Http\Resources\JobSummaryResource;
use ArgusApi\Http\Support\ApiResponse;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class SavedSearchResultsController
{
    public function __construct(
        private SavedSearchService $service,
        private Gate $gate,
    ) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        // Returns job data, so it is gated by view-jobs rather than the
        // saved-search management ability.
        Authorize::check($this->gate, $request->user(), Abilities::VIEW_JOBS);

        if ($this->service->find($id) === null) {
            throw new NotFoundException("Unknown saved search [{$id}].");
        }

        $results = $this->service->results($id);

        return ApiResponse::ok(
            JobSummaryResource::collection($results),
            ['savedSearchId' => $id, 'count' => count($results)],
        );
    }
}
```

- [ ] **Step 10: Update `routes/argus-api.php`** to the full set so far

```php
<?php

declare(strict_types=1);

use ArgusApi\Http\Controllers\FailureGroupController;
use ArgusApi\Http\Controllers\JobHistoryController;
use ArgusApi\Http\Controllers\SavedSearches\CreateSavedSearchController;
use ArgusApi\Http\Controllers\SavedSearches\DeleteSavedSearchController;
use ArgusApi\Http\Controllers\SavedSearches\ListSavedSearchesController;
use ArgusApi\Http\Controllers\SavedSearches\SavedSearchResultsController;
use ArgusApi\Http\Controllers\SavedSearches\ShowSavedSearchController;
use ArgusApi\Http\Controllers\SavedSearches\UpdateSavedSearchController;
use ArgusApi\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::post('search', SearchController::class)->name('argus-api.search');
Route::get('jobs/{jobUuid}/history', JobHistoryController::class)->name('argus-api.jobs.history');
Route::post('failures', FailureGroupController::class)->name('argus-api.failures');

Route::get('saved-searches', ListSavedSearchesController::class)->name('argus-api.saved-searches.index');
Route::post('saved-searches', CreateSavedSearchController::class)->name('argus-api.saved-searches.store');
Route::get('saved-searches/{id}', ShowSavedSearchController::class)->name('argus-api.saved-searches.show');
Route::put('saved-searches/{id}', UpdateSavedSearchController::class)->name('argus-api.saved-searches.update');
Route::delete('saved-searches/{id}', DeleteSavedSearchController::class)->name('argus-api.saved-searches.destroy');
Route::get('saved-searches/{id}/results', SavedSearchResultsController::class)->name('argus-api.saved-searches.results');
```

- [ ] **Step 11: Run the test to verify it passes**

Run: `./vendor/bin/phpunit --filter SavedSearchEndpointTest`
Expected: PASS (8 tests).

- [ ] **Step 12: Format and commit**

```bash
./vendor/bin/pint --dirty
git add src/ routes/ tests/Feature/SavedSearchEndpointTest.php
git commit -m "feat: saved-search CRUD and results endpoints

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 11: Alert-rule CRUD

**Files:**
- Create: `src/Http/Requests/AlertRuleRequest.php`
- Create: `src/Http/Controllers/AlertRules/ListAlertRulesController.php`
- Create: `src/Http/Controllers/AlertRules/ListSavedSearchAlertRulesController.php`
- Create: `src/Http/Controllers/AlertRules/CreateAlertRuleController.php`
- Create: `src/Http/Controllers/AlertRules/ShowAlertRuleController.php`
- Create: `src/Http/Controllers/AlertRules/UpdateAlertRuleController.php`
- Create: `src/Http/Controllers/AlertRules/DeleteAlertRuleController.php`
- Modify: `routes/argus-api.php`
- Test: `tests/Feature/AlertRuleEndpointTest.php`

- [ ] **Step 1: Write the failing test `tests/Feature/AlertRuleEndpointTest.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Feature;

use ArgusApi\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class AlertRuleEndpointTest extends TestCase
{
    private function savedSearchId(): string
    {
        $response = $this->actingAsUser()->postJson('argus-api/saved-searches', [
            'name' => 'Failed emails',
            'filter' => ['queue' => 'emails', 'status' => 'failed'],
        ]);

        return (string) $response->json('data.id');
    }

    /** @return array{0:string,1:string} [savedSearchId, alertRuleId] */
    private function createRule(): array
    {
        $ssId = $this->savedSearchId();
        $response = $this->actingAsUser()->postJson("argus-api/saved-searches/{$ssId}/alert-rules", [
            'name' => 'High failures',
            'threshold' => 10,
            'windowSeconds' => 300,
            'cooldownSeconds' => 600,
            'sinks' => ['slack'],
        ]);

        return [$ssId, (string) $response->json('data.id')];
    }

    #[Test]
    public function it_creates_a_rule_attached_to_a_saved_search(): void
    {
        $ssId = $this->savedSearchId();

        $response = $this->actingAsUser()->postJson("argus-api/saved-searches/{$ssId}/alert-rules", [
            'name' => 'High failures',
            'threshold' => 10,
            'windowSeconds' => 300,
            'cooldownSeconds' => 600,
            'sinks' => ['slack'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.savedSearchId', $ssId)
            ->assertJsonPath('data.threshold', 10)
            ->assertJsonPath('data.state', 'ok')
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.sinks', ['slack']);
    }

    #[Test]
    public function creating_a_rule_for_an_unknown_saved_search_is_404(): void
    {
        $this->actingAsUser()->postJson('argus-api/saved-searches/999/alert-rules', [
            'name' => 'x',
            'threshold' => 1,
            'windowSeconds' => 60,
            'cooldownSeconds' => 60,
            'sinks' => [],
        ])->assertStatus(404);
    }

    #[Test]
    public function it_lists_rules_for_a_saved_search(): void
    {
        [$ssId] = $this->createRule();

        $this->actingAsUser()->getJson("argus-api/saved-searches/{$ssId}/alert-rules")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.savedSearchId', $ssId);
    }

    #[Test]
    public function it_lists_all_rules(): void
    {
        $this->createRule();

        $this->actingAsUser()->getJson('argus-api/alert-rules')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.count', 1);
    }

    #[Test]
    public function it_shows_updates_and_deletes_a_rule(): void
    {
        [, $id] = $this->createRule();

        $this->actingAsUser()->getJson("argus-api/alert-rules/{$id}")
            ->assertOk()->assertJsonPath('data.id', $id);

        $this->actingAsUser()->putJson("argus-api/alert-rules/{$id}", [
            'name' => 'Renamed',
            'threshold' => 20,
            'windowSeconds' => 120,
            'cooldownSeconds' => 120,
            'sinks' => ['webhook'],
            'enabled' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.threshold', 20)
            ->assertJsonPath('data.enabled', false);

        $this->actingAsUser()->deleteJson("argus-api/alert-rules/{$id}")->assertNoContent();
        $this->actingAsUser()->getJson("argus-api/alert-rules/{$id}")->assertStatus(404);
    }

    #[Test]
    public function create_validates_required_fields(): void
    {
        $ssId = $this->savedSearchId();

        $this->actingAsUser()->postJson("argus-api/saved-searches/{$ssId}/alert-rules", ['name' => 'x'])
            ->assertStatus(422)
            ->assertJsonPath('error.type', 'validation');
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `./vendor/bin/phpunit --filter AlertRuleEndpointTest`
Expected: FAIL — routes/controllers missing.

- [ ] **Step 3: Write `src/Http/Requests/AlertRuleRequest.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Http\Requests;

final class AlertRuleRequest extends ApiFormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'threshold' => ['required', 'integer', 'min:0'],
            'windowSeconds' => ['required', 'integer', 'min:1'],
            'cooldownSeconds' => ['required', 'integer', 'min:0'],
            'sinks' => ['present', 'array'],
            'sinks.*' => ['string'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}
```

- [ ] **Step 4: Write `src/Http/Controllers/AlertRules/ListAlertRulesController.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers\AlertRules;

use Argus\Alerting\AlertService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Http\Resources\AlertRuleResource;
use ArgusApi\Http\Support\ApiResponse;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ListAlertRulesController
{
    public function __construct(
        private AlertService $service,
        private Gate $gate,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        Authorize::check($this->gate, $request->user(), Abilities::MANAGE_ALERTS);

        $all = $this->service->all();

        return ApiResponse::ok(AlertRuleResource::collection($all), ['count' => count($all)]);
    }
}
```

- [ ] **Step 5: Write `src/Http/Controllers/AlertRules/ListSavedSearchAlertRulesController.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers\AlertRules;

use Argus\Alerting\AlertService;
use Argus\SavedSearches\SavedSearchService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Exceptions\NotFoundException;
use ArgusApi\Http\Resources\AlertRuleResource;
use ArgusApi\Http\Support\ApiResponse;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ListSavedSearchAlertRulesController
{
    public function __construct(
        private AlertService $alerts,
        private SavedSearchService $savedSearches,
        private Gate $gate,
    ) {}

    public function __invoke(Request $request, string $savedSearchId): JsonResponse
    {
        Authorize::check($this->gate, $request->user(), Abilities::MANAGE_ALERTS);

        if ($this->savedSearches->find($savedSearchId) === null) {
            throw new NotFoundException("Unknown saved search [{$savedSearchId}].");
        }

        $rules = $this->alerts->forSavedSearch($savedSearchId);

        return ApiResponse::ok(
            AlertRuleResource::collection($rules),
            ['savedSearchId' => $savedSearchId, 'count' => count($rules)],
        );
    }
}
```

- [ ] **Step 6: Write `src/Http/Controllers/AlertRules/CreateAlertRuleController.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers\AlertRules;

use Argus\Alerting\AlertService;
use Argus\SavedSearches\SavedSearchService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Exceptions\NotFoundException;
use ArgusApi\Http\Requests\AlertRuleRequest;
use ArgusApi\Http\Resources\AlertRuleResource;
use ArgusApi\Http\Support\ApiResponse;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;

final readonly class CreateAlertRuleController
{
    public function __construct(
        private AlertService $alerts,
        private SavedSearchService $savedSearches,
        private Gate $gate,
    ) {}

    public function __invoke(AlertRuleRequest $request, string $savedSearchId): JsonResponse
    {
        Authorize::check($this->gate, $request->user(), Abilities::MANAGE_ALERTS);

        if ($this->savedSearches->find($savedSearchId) === null) {
            throw new NotFoundException("Unknown saved search [{$savedSearchId}].");
        }

        $v = $request->validated();
        $rule = $this->alerts->attach(
            $savedSearchId,
            $v['name'],
            (int) $v['threshold'],
            (int) $v['windowSeconds'],
            (int) $v['cooldownSeconds'],
            $v['sinks'],
            $v['enabled'] ?? true,
        );

        return ApiResponse::created(AlertRuleResource::toArray($rule));
    }
}
```

- [ ] **Step 7: Write `src/Http/Controllers/AlertRules/ShowAlertRuleController.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers\AlertRules;

use Argus\Alerting\AlertService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Exceptions\NotFoundException;
use ArgusApi\Http\Resources\AlertRuleResource;
use ArgusApi\Http\Support\ApiResponse;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ShowAlertRuleController
{
    public function __construct(
        private AlertService $service,
        private Gate $gate,
    ) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        Authorize::check($this->gate, $request->user(), Abilities::MANAGE_ALERTS);

        $rule = $this->service->find($id);
        if ($rule === null) {
            throw new NotFoundException("Unknown alert rule [{$id}].");
        }

        return ApiResponse::ok(AlertRuleResource::toArray($rule));
    }
}
```

- [ ] **Step 8: Write `src/Http/Controllers/AlertRules/UpdateAlertRuleController.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers\AlertRules;

use Argus\Alerting\AlertService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Exceptions\NotFoundException;
use ArgusApi\Http\Requests\AlertRuleRequest;
use ArgusApi\Http\Resources\AlertRuleResource;
use ArgusApi\Http\Support\ApiResponse;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;

final readonly class UpdateAlertRuleController
{
    public function __construct(
        private AlertService $service,
        private Gate $gate,
    ) {}

    public function __invoke(AlertRuleRequest $request, string $id): JsonResponse
    {
        Authorize::check($this->gate, $request->user(), Abilities::MANAGE_ALERTS);

        $existing = $this->service->find($id);
        if ($existing === null) {
            throw new NotFoundException("Unknown alert rule [{$id}].");
        }

        $v = $request->validated();
        $rule = $this->service->update(
            $id,
            $v['name'],
            (int) $v['threshold'],
            (int) $v['windowSeconds'],
            (int) $v['cooldownSeconds'],
            $v['sinks'],
            $v['enabled'] ?? $existing->enabled,
        );

        return ApiResponse::ok(AlertRuleResource::toArray($rule));
    }
}
```

- [ ] **Step 9: Write `src/Http/Controllers/AlertRules/DeleteAlertRuleController.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers\AlertRules;

use Argus\Alerting\AlertService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Exceptions\NotFoundException;
use ArgusApi\Http\Support\ApiResponse;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class DeleteAlertRuleController
{
    public function __construct(
        private AlertService $service,
        private Gate $gate,
    ) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        Authorize::check($this->gate, $request->user(), Abilities::MANAGE_ALERTS);

        if ($this->service->find($id) === null) {
            throw new NotFoundException("Unknown alert rule [{$id}].");
        }

        $this->service->delete($id);

        return ApiResponse::noContent();
    }
}
```

- [ ] **Step 10: Append the alert-rule routes to `routes/argus-api.php`**

Add these imports and route lines (keep all existing routes from Task 10):

```php
use ArgusApi\Http\Controllers\AlertRules\CreateAlertRuleController;
use ArgusApi\Http\Controllers\AlertRules\DeleteAlertRuleController;
use ArgusApi\Http\Controllers\AlertRules\ListAlertRulesController;
use ArgusApi\Http\Controllers\AlertRules\ListSavedSearchAlertRulesController;
use ArgusApi\Http\Controllers\AlertRules\ShowAlertRuleController;
use ArgusApi\Http\Controllers\AlertRules\UpdateAlertRuleController;
```

```php
Route::get('saved-searches/{savedSearchId}/alert-rules', ListSavedSearchAlertRulesController::class)->name('argus-api.saved-searches.alert-rules.index');
Route::post('saved-searches/{savedSearchId}/alert-rules', CreateAlertRuleController::class)->name('argus-api.saved-searches.alert-rules.store');

Route::get('alert-rules', ListAlertRulesController::class)->name('argus-api.alert-rules.index');
Route::get('alert-rules/{id}', ShowAlertRuleController::class)->name('argus-api.alert-rules.show');
Route::put('alert-rules/{id}', UpdateAlertRuleController::class)->name('argus-api.alert-rules.update');
Route::delete('alert-rules/{id}', DeleteAlertRuleController::class)->name('argus-api.alert-rules.destroy');
```

- [ ] **Step 11: Run the test to verify it passes**

Run: `./vendor/bin/phpunit --filter AlertRuleEndpointTest`
Expected: PASS (7 tests).

- [ ] **Step 12: Format and commit**

```bash
./vendor/bin/pint --dirty
git add src/ routes/ tests/Feature/AlertRuleEndpointTest.php
git commit -m "feat: alert-rule CRUD endpoints attached to saved searches

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 12: Authorization feature tests (auth seam end to end)

The route group captures its middleware when routes register (at `boot()`, before
any test method runs), so a test cannot switch the auth middleware on by mutating
config in its body. The 401 case therefore lives in its own class whose
`defineEnvironment()` sets the auth middleware before routes register. The 403 and
default-allow cases work in the body, because gates are evaluated per request: the
injected `Gate` is a container singleton, so redefining an ability before the call
changes the verdict the controller sees.

**Files:**
- Create: `tests/Feature/AuthorizationTest.php`
- Create: `tests/Feature/UnauthenticatedTest.php`

- [ ] **Step 1: Write the test `tests/Feature/AuthorizationTest.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Feature;

use ArgusApi\Authorization\Abilities;
use ArgusApi\Tests\TestCase;
use Illuminate\Contracts\Auth\Access\Gate;
use PHPUnit\Framework\Attributes\Test;

final class AuthorizationTest extends TestCase
{
    #[Test]
    public function an_authenticated_user_passes_the_default_gate(): void
    {
        $this->actingAsUser()->postJson('argus-api/search', ['queue' => 'emails'])->assertOk();
    }

    #[Test]
    public function an_authenticated_user_without_the_gate_gets_403(): void
    {
        $this->app->make(Gate::class)->define(Abilities::VIEW_JOBS, fn ($user) => false);

        $this->actingAsUser()->postJson('argus-api/search', ['queue' => 'emails'])
            ->assertStatus(403)
            ->assertJsonPath('error.type', 'forbidden')
            ->assertJsonPath('error.details.ability', 'view-jobs');
    }

    #[Test]
    public function each_endpoint_is_guarded_by_its_own_gate(): void
    {
        $gate = $this->app->make(Gate::class);
        $gate->define(Abilities::VIEW_FAILURES, fn ($user) => false);
        $gate->define(Abilities::MANAGE_SAVED_SEARCHES, fn ($user) => false);
        $gate->define(Abilities::MANAGE_ALERTS, fn ($user) => false);

        $this->actingAsUser()->postJson('argus-api/failures', [])->assertStatus(403);
        $this->actingAsUser()->getJson('argus-api/saved-searches')->assertStatus(403);
        $this->actingAsUser()->getJson('argus-api/alert-rules')->assertStatus(403);
    }
}
```

- [ ] **Step 2: Write the test `tests/Feature/UnauthenticatedTest.php`**

The auth middleware must be wired before routes register, so it is set in
`defineEnvironment()` (which runs during app setup), not in the test body.

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Feature;

use ArgusApi\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class UnauthenticatedTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Simulate the consuming app plugging its own auth guard into the seam.
        // 'auth' uses the default web guard, so no extra package is needed; a
        // guest hitting a JSON route is rejected with 401 before any controller.
        $app['config']->set('argus-api.middleware', ['auth']);
    }

    #[Test]
    public function an_unauthenticated_request_is_rejected_before_the_controller(): void
    {
        $this->postJson('argus-api/search', ['queue' => 'emails'])->assertStatus(401);
    }

    #[Test]
    public function an_authenticated_request_passes_the_middleware(): void
    {
        $this->actingAsUser()->postJson('argus-api/search', ['queue' => 'emails'])->assertOk();
    }
}
```

- [ ] **Step 3: Run them to verify they pass**

Run: `./vendor/bin/phpunit --filter "AuthorizationTest|UnauthenticatedTest"`
Expected: PASS (5 tests). If the 401 case returns 200, the auth middleware was not
applied at registration: confirm it is set in `defineEnvironment()`, not the test body.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/AuthorizationTest.php tests/Feature/UnauthenticatedTest.php
git commit -m "test: auth seam (401 unauthenticated, 403 ungated, per-gate guards)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 13: Storage-isolation guarantee test

**Files:**
- Create: `tests/Feature/StorageIsolationTest.php`

- [ ] **Step 1: Write the test `tests/Feature/StorageIsolationTest.php`**

```php
<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Feature;

use Argus\Query\JobSummary;
use ArgusApi\Tests\TestCase;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;

final class StorageIsolationTest extends TestCase
{
    #[Test]
    public function the_search_controller_routes_through_the_query_seam_only(): void
    {
        // The only data source is the fake bound at the core's TransitionQuery
        // contract. A response built from it proves the controller did not reach
        // past the service into storage.
        $this->transitions->summaries = [
            new JobSummary('sentinel-uuid', 'App\\Jobs\\Send', 'emails', 'acme', 'failed', 1, CarbonImmutable::parse('2026-05-01T10:00:00+00:00'), null, null, null),
        ];

        $this->actingAsUser()->postJson('argus-api/search', ['queue' => 'emails', 'status' => 'failed'])
            ->assertOk()
            ->assertJsonPath('data.0.jobUuid', 'sentinel-uuid');

        // And the controller passed the request through as a real JobFilter.
        $this->assertNotNull($this->transitions->lastFilter);
        $this->assertSame('emails', $this->transitions->lastFilter->queue);
        $this->assertSame('failed', $this->transitions->lastFilter->status?->value);
    }

    #[Test]
    public function package_source_never_references_storage_or_the_database(): void
    {
        $forbidden = [
            'Illuminate\\Database',
            'Illuminate\\Support\\Facades\\DB',
            'Argus\\Storage',
            'ConnectionInterface',
        ];

        $srcDir = __DIR__.'/../../src';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($srcDir, \FilesystemIterator::SKIP_DOTS));

        $offenders = [];
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            foreach ($forbidden as $needle) {
                if (str_contains($contents, $needle)) {
                    $offenders[] = $file->getPathname().' references '.$needle;
                }
            }
        }

        $this->assertSame([], $offenders, "Package source must not touch storage:\n".implode("\n", $offenders));
    }
}
```

- [ ] **Step 2: Run it to verify it passes**

Run: `./vendor/bin/phpunit --filter StorageIsolationTest`
Expected: PASS (2 tests).

- [ ] **Step 3: Run the full suite**

Run: `./vendor/bin/phpunit`
Expected: PASS — all unit and feature tests green (roughly 40 tests across the suites).

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/StorageIsolationTest.php
git commit -m "test: assert the API never touches storage, only the query seam

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 14: README with API reference and TypeScript contract

**Files:**
- Create: `README.md`

- [ ] **Step 1: Write `README.md`**

````markdown
# Queue Observability API (`acme/queue-observability-api`)

A JSON HTTP API over the [`acme/argus`](../queue-observability) queue-observability
core. It exposes the core's read and management services and nothing else: it holds
no storage knowledge, emits no SQL, and never touches a database. Dependency
direction is one way: `React client -> this API -> acme/argus -> storage`.

## Installation

```bash
composer require acme/queue-observability-api
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
composer install   # resolves acme/argus via the local path repository
./vendor/bin/phpunit
./vendor/bin/pint
```

Tests run against in-memory fakes bound at the core's storage contracts; no
Postgres or Redis is required.
````

- [ ] **Step 2: Commit**

```bash
git add README.md
git commit -m "docs: API reference and TypeScript contract for Phase 5

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Final verification

- [ ] **Run the complete suite and formatter**

Run: `./vendor/bin/phpunit && ./vendor/bin/pint --test`
Expected: all tests PASS; Pint reports no style issues.

- [ ] **Confirm deliverables against the spec**
  - Installable package depending on the core: Task 1.
  - Configurable auth-middleware seam: Tasks 3, 12.
  - Publishable authorization gates: Task 5.
  - JSON endpoints (search, history, failures, saved-search CRUD + results, alert CRUD): Tasks 8-11.
  - Consistent envelope and error shape: Task 4, exercised throughout.
  - Typed contract + API reference in README: Task 14.
  - Passing feature tests including the storage-isolation guarantee: Tasks 8-13.
```

---

## Notes for the implementer

- The core (`acme/argus`) is read-only here. Never edit it; only call its public
  services and DTOs.
- `JobSummary::$status` is a plain string; `TransitionRecord::$transition` is a
  `TransitionType` enum (render `->value`). Do not mix them up.
- The `auth:sanctum` default only resolves if the host app installed Sanctum. Tests
  use `['auth']` (the default web guard) to simulate the app's seam, which needs no
  extra package.
- Saved-search and alert-rule ids are strings. Route params are strings; no integer
  casting, no route-model binding.
