<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

#[FailOnUnknownFields]
class BrowseAdminUsersRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const ALLOWED_QUERY_FIELDS = [
        'search',
        'role',
        'status',
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
        $search =
            $this->query(
                'search'
            );

        if (
            is_string(
                $search
            )
        ) {
            $this->merge([
                'search' =>
                    trim(
                        $search
                    ),
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

            /*
             * Administrator accounts are intentionally excluded.
             */
            'role' => [
                'sometimes',
                Rule::in([
                    UserRole::Customer->value,
                    UserRole::ShopOwner->value,
                ]),
            ],

            'status' => [
                'sometimes',
                Rule::enum(
                    UserStatus::class
                ),
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