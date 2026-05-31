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
