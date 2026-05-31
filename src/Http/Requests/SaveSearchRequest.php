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
            // 'present' not 'required': an empty (match-all) filter is a valid
            // saved search, and 'required' would reject {} and also pre-empt the
            // 404 path when updating an unknown id with an empty filter body.
            'filter' => ['present', 'array'],
        ], FilterRules::prefixed('filter'));
    }
}
