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
