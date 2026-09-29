<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class GetAvailableSlotsRequest extends FormRequest
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
     * Normalize incoming query parameters.
     */
    protected function prepareForValidation(): void
    {
        if (
            is_string(
                $this->input('date')
            )
        ) {
            $this->merge([
                'date' => trim(
                    $this->input('date')
                ),
            ]);
        }
    }

    /**
     * Validate the requested date.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => [
                'required',
                'date_format:Y-m-d',
            ],
        ];
    }
}