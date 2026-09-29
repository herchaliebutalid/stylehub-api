<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class GetAvailableDatesRequest extends FormRequest
{
    /**
     * Authentication and customer authorization are handled
     * by the route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validate availability-date query parameters.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'days' => [
                'sometimes',
                'integer',
                'min:1',
                'max:60',
            ],
        ];
    }
}