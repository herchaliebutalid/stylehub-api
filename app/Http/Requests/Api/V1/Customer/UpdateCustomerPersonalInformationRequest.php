<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Models\User;
use App\Support\PhilippinePhoneNumber;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class UpdateCustomerPersonalInformationRequest extends FormRequest
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

            'phone_number' =>
                is_string(
                    $this->input(
                        'phone_number'
                    )
                )
                    ? PhilippinePhoneNumber::normalize(
                        $this->input(
                            'phone_number'
                        )
                    )
                    : $this->input(
                        'phone_number'
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

            'phone_number' => [
                'required',
                'string',
                'max:20',
                'regex:/^\+639\d{9}$/',

                Rule::unique(
                    User::class,
                    'phone_number'
                )->ignore(
                    $this->user()?->id
                ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Sensitive Identity Changes
            |--------------------------------------------------------------------------
            |
            | The service requires this only when the normalized email or phone
            | number is actually different from the current account.
            |
            */

            'current_password' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone_number.regex' =>
                'The phone number must be a valid Philippine mobile number.',

            'phone_number.unique' =>
                'This phone number is already associated with another account.',

            'email.unique' =>
                'This email address is already associated with another account.',
        ];
    }

    private function cleanString(
        mixed $value
    ): mixed {
        if (
            ! is_string(
                $value
            )
        ) {
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
            is_string(
                $cleaned
            )
            && $cleaned === ''
        ) {
            return null;
        }

        return $cleaned;
    }
}