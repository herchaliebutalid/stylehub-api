<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\ShopAdministrativeStatus;
use App\Enums\ShopApprovalStatus;
use App\Enums\ShopAvailabilityStatus;
use App\Enums\ShopType;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

#[FailOnUnknownFields]
class BrowseAdminShopsRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const ALLOWED_QUERY_FIELDS = [
        'search',
        'approval_status',
        'administrative_status',
        'type',
        'availability_status',
        'city',
        'province',
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
        foreach (
            [
                'search',
                'city',
                'province',
            ] as $field
        ) {
            $value =
                $this->query(
                    $field
                );

            if (
                ! is_string(
                    $value
                )
            ) {
                continue;
            }

            $trimmed =
                trim(
                    $value
                );

            $this->merge([
                $field =>
                    $trimmed,
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

            'approval_status' => [
                'sometimes',
                Rule::enum(
                    ShopApprovalStatus::class
                ),
            ],

            'administrative_status' => [
                'sometimes',
                Rule::enum(
                    ShopAdministrativeStatus::class
                ),
            ],

            'type' => [
                'sometimes',
                Rule::enum(
                    ShopType::class
                ),
            ],

            'availability_status' => [
                'sometimes',
                Rule::enum(
                    ShopAvailabilityStatus::class
                ),
            ],

            'city' => [
                'sometimes',
                'string',
                'min:1',
                'max:100',
            ],

            'province' => [
                'sometimes',
                'string',
                'min:1',
                'max:100',
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