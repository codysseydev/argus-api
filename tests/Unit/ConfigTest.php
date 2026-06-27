<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Unit;

use ArgusApi\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ConfigTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        // Don't override guard or middleware so the package defaults are visible.
        $app['config']->set('argus.store', 'postgres');
        $app['config']->set('argus.schedule.enabled', false);
        $app['config']->set('argus.alerting.enabled', false);
    }

    #[Test]
    public function it_merges_default_config(): void
    {
        $this->assertSame('argus-api', config('argus-api.prefix'));
        $this->assertSame(100, config('argus-api.pagination.default_limit'));
        $this->assertSame(500, config('argus-api.pagination.max_limit'));
        $this->assertTrue(config('argus-api.authorization.allow_by_default'));
    }

    #[Test]
    public function guard_defaults_to_sanctum(): void
    {
        $this->assertSame('sanctum', config('argus-api.guard'));
    }

    #[Test]
    public function middleware_defaults_to_empty(): void
    {
        $this->assertSame([], config('argus-api.middleware'));
    }
}
