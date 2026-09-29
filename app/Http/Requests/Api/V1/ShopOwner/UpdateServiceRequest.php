<?php

namespace App\Http\Requests\Api\V1\ShopOwner;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class UpdateServiceRequest extends FormRequest
{
    /**
     * Role and shop approval authorization are handled by middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize only fields that were actually supplied.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->exists('name')) {
            $normalized['name'] = $this->cleanString(
                $this->input('name')
            );
        }

        if ($this->exists('description')) {
            $normalized['description'] =
                $this->cleanNullableString(
                    $this->input('description')
                );
        }

        if ($this->exists('price')) {
            $normalized['price'] =
                is_string($this->input('price'))
                    ? trim($this->input('price'))
                    : $this->input('price');
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * Validate service updates.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:120',
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:2000',
            ],

            'price' => [
                'sometimes',
                'required',
                'numeric',
                'decimal:0,2',
                'gt:0',
                'max:99999999.99',
            ],

            'duration_minutes' => [
                'sometimes',
                'required',
                'integer',
                'min:5',
                'max:1440',
            ],

            'is_active' => [
                'sometimes',
                'required',
                'boolean',
            ],
        ];
    }

    /**
     * Human-friendly validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'price.gt' =>
                'The service price must be greater than zero.',

            'price.decimal' =>
                'The service price may contain at most two decimal places.',

            'duration_minutes.min' =>
                'The service duration must be at least 5 minutes.',

            'duration_minutes.max' =>
                'The service duration may not exceed 1440 minutes.',
        ];
    }

    private function cleanString(mixed $value): mixed
    {
        return is_string($value)
            ? trim($value)
            : $value;
    }

    private function cleanNullableString(
        mixed $value
    ): mixed {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }
}