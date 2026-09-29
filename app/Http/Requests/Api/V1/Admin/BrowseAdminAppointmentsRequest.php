<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\AppointmentStatus;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

#[FailOnUnknownFields]
class BrowseAdminAppointmentsRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const ALLOWED_QUERY_FIELDS = [
        'search',
        'status',
        'shop_id',
        'customer_id',
        'date_from',
        'date_to',
        'page',
        'per_page',
    ];

    public function authorize(): bool
    {
        /*
         * Authorization is handled by:
         *
         * auth:sanctum
         * account.active
         * role.admin
         */
        return true;
    }

    protected function prepareForValidation(): void
    {
        $search = $this->query('search');

        if (is_string($search)) {
            $this->merge([
                'search' => trim($search),
            ]);
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
                'string',
                'min:1',
                'max:100',
            ],

            'status' => [
                'sometimes',
                Rule::enum(
                    AppointmentStatus::class
                ),
            ],

            'shop_id' => [
                'sometimes',
                'integer',
                'min:1',
            ],

            'customer_id' => [
                'sometimes',
                'integer',
                'min:1',
            ],

            'date_from' => [
                'sometimes',
                'date_format:Y-m-d',
            ],

            'date_to' => [
                'sometimes',
                'date_format:Y-m-d',
                'after_or_equal:date_from',
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