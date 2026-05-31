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
