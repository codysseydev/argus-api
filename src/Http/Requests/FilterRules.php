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
