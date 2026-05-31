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
