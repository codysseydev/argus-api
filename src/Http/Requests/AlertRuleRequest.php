<?php

declare(strict_types=1);

namespace ArgusApi\Http\Requests;

use Argus\Alerting\AlertComparison;
use Argus\Alerting\AlertConditionType;
use Illuminate\Validation\Rule;

final class AlertRuleRequest extends ApiFormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'threshold' => ['required', 'integer', 'min:0'],
            'conditionType' => ['sometimes', Rule::enum(AlertConditionType::class)],
            'comparison' => ['sometimes', Rule::enum(AlertComparison::class)],
            // stuckSeconds only applies to the stuck_count condition: require it
            // there, and reject it (when non-empty) for any other condition.
            'stuckSeconds' => [
                'nullable',
                'integer',
                'min:1',
                'required_if:conditionType,stuck_count',
                'prohibited_unless:conditionType,stuck_count',
            ],
            'windowSeconds' => ['required', 'integer', 'min:1'],
            'cooldownSeconds' => ['required', 'integer', 'min:0'],
            'sinks' => ['present', 'array'],
            'sinks.*' => ['string'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}
