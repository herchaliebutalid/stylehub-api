<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

#[FailOnUnknownFields]
class NearbyShopsRequest extends FormRequest
{
    /**
     * Query parameters explicitly supported by nearby-shop discovery.
     *
     * @var list<string>
     */
    private const ALLOWED_QUERY_FIELDS = [
        'latitude',
        'longitude',
        'radius_km',
        'limit',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize numeric query strings before validation.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (
            self::ALLOWED_QUERY_FIELDS as $field
        ) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = $this->input($field);

            $normalized[$field] =
                is_string($value)
                    ? trim($value)
                    : $value;
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'latitude' => [
                'required',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'required',
                'numeric',
                'between:-180,180',
            ],

            'radius_km' => [
                'sometimes',
                'numeric',
                'between:1,100',
            ],

            'limit' => [
                'sometimes',
                'integer',
                'min:1',
                'max:50',
            ],
        ];
    }

    /**
     * Reject unsupported query parameters explicitly.
     */
    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(
            function (
                Validator $validator
            ): void {
                foreach (
                    array_keys(
                        $this->query()
                    ) as $field
                ) {
                    if (
                        in_array(
                            $field,
                            self::ALLOWED_QUERY_FIELDS,
                            true
                        )
                    ) {
                        continue;
                    }

                    $validator
                        ->errors()
                        ->add(
                            $field,
                            "The {$field} query parameter is not allowed."
                        );
                }
            }
        );
    }
}