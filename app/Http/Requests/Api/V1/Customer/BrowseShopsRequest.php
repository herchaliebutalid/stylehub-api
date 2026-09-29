<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Enums\ShopAvailabilityStatus;
use App\Enums\ShopType;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

#[FailOnUnknownFields]
class BrowseShopsRequest extends FormRequest
{
    /**
     * Query parameters explicitly supported by the customer
     * shop-discovery endpoint.
     *
     * Any other query parameter is rejected rather than silently ignored.
     *
     * @var list<string>
     */
    private const ALLOWED_QUERY_FIELDS = [
        'search',
        'type',
        'city',
        'province',
        'availability_status',
        'page',
        'per_page',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize supported customer search/filter input.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (
            [
                'search',
                'city',
                'province',
                'type',
                'availability_status',
            ] as $field
        ) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = $this->input(
                $field
            );

            $normalized[$field] =
                is_string($value)
                    ? trim($value)
                    : $value;
        }

        if ($normalized !== []) {
            $this->merge(
                $normalized
            );
        }
    }

    /**
     * Customer shop-discovery validation.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => [
                'sometimes',
                'nullable',
                'string',
                'min:1',
                'max:100',
            ],

            'type' => [
                'sometimes',
                'nullable',
                new Enum(
                    ShopType::class
                ),
            ],

            'city' => [
                'sometimes',
                'nullable',
                'string',
                'min:1',
                'max:100',
            ],

            'province' => [
                'sometimes',
                'nullable',
                'string',
                'min:1',
                'max:100',
            ],

            'availability_status' => [
                'sometimes',
                'nullable',
                new Enum(
                    ShopAvailabilityStatus::class
                ),
            ],

            'page' => [
                'sometimes',
                'integer',
                'min:1',
            ],

            /*
            |--------------------------------------------------------------------------
            | Pagination Protection
            |--------------------------------------------------------------------------
            |
            | Customers cannot request an unlimited result set.
            |
            */

            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:50',
            ],
        ];
    }

    /**
     * Explicitly reject unknown GET/query parameters.
     *
     * FailOnUnknownFields protects normal request input, but customer browse
     * filters arrive through the query string. We therefore enforce the
     * allow-list here as defense in depth.
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