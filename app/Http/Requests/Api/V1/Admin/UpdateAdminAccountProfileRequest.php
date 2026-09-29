<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class UpdateAdminAccountProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' =>
                $this->cleanString(
                    $this->input(
                        'first_name'
                    )
                ),

            'middle_name' =>
                $this->cleanNullableString(
                    $this->input(
                        'middle_name'
                    )
                ),

            'last_name' =>
                $this->cleanString(
                    $this->input(
                        'last_name'
                    )
                ),

            'email' =>
                is_string(
                    $this->input(
                        'email'
                    )
                )
                    ? mb_strtolower(
                        trim(
                            $this->input(
                                'email'
                            )
                        )
                    )
                    : $this->input(
                        'email'
                    ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => [
                'required',
                'string',
                'min:1',
                'max:100',
            ],

            'middle_name' => [
                'nullable',
                'string',
                'max:100',
            ],

            'last_name' => [
                'required',
                'string',
                'min:1',
                'max:100',
            ],

            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',

                Rule::unique(
                    User::class,
                    'email'
                )->ignore(
                    $this->user()?->id
                ),
            ],
        ];
    }

    private function cleanString(
        mixed $value
    ): mixed {
        if (! is_string($value)) {
            return $value;
        }

        return trim(
            preg_replace(
                '/\s+/u',
                ' ',
                $value
            ) ?? $value
        );
    }

    private function cleanNullableString(
        mixed $value
    ): mixed {
        $cleaned =
            $this->cleanString(
                $value
            );

        if (
            is_string($cleaned)
            && $cleaned === ''
        ) {
            return null;
        }

        return $cleaned;
    }
}