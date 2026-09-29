<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

#[FailOnUnknownFields]
class BrowsePromosRequest extends FormRequest
{
    /**
     * Query-string parameters supported by promotion discovery.
     *
     * @var list<string>
     */
    private const ALLOWED_QUERY_FIELDS = [
        'search',
        'shop_id',
        'page',
        'per_page',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize customer query input.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->exists('search')) {
            $search =
                $this->input('search');

            $normalized['search'] =
                is_string($search)
                    ? trim($search)
                    : $search;
        }

        if ($normalized !== []) {
            $this->merge(
                $normalized
            );
        }
    }

    /**
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

            'shop_id' => [
                'sometimes',
                'integer',
                'min:1',
            ],

            'page' => [
                'sometimes',
                'integer',
                'min:1',
            ],

            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:50',
            ],
        ];
    }

    /**
     * Explicitly reject unsupported query parameters.
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