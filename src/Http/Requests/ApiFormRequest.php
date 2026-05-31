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
