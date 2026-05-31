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
