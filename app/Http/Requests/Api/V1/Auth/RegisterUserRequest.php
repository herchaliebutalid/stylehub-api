<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Models\User;
use App\Support\PhilippinePhoneNumber;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

#[FailOnUnknownFields]
class RegisterUserRequest extends FormRequest
{
    /**
     * Public registration is allowed.
     *
     * The controller, not the client, determines the user's role.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize user input before validation.
     */
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

            'device_name' =>
                $this->cleanString(
                    $this->input(
                        'device_name'
                    )
                ),
        ]);
    }

    /**
     * Validation rules for customer registration.
     *
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
                ),
            ],

            'phone_number' => [
                'required',
                'string',
                'max:20',
                'regex:/^\+639\d{9}$/',

                /*
                |--------------------------------------------------------------------------
                | Account Phone Numbers Are Unique
                |--------------------------------------------------------------------------
                */

                Rule::unique(
                    User::class,
                    'phone_number'
                ),
            ],

            'password' => [
                'required',
                'string',
                'confirmed',
                Password::defaults(),
            ],

            'password_confirmation' => [
                'required',
                'string',
                'max:64',
            ],

            'device_name' => [
                'required',
                'string',
                'min:2',
                'max:100',
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
            'phone_number.regex' =>
                'The phone number must be a valid Philippine mobile number.',

            'phone_number.unique' =>
                'This phone number is already associated with another account.',

            'password.confirmed' =>
                'The password confirmation does not match.',
        ];
    }

    private function cleanString(
        mixed $value
    ): mixed {
        return is_string(
            $value
        )
            ? trim(
                $value
            )
            : $value;
    }

    private function cleanNullableString(
        mixed $value
    ): mixed {
        if (
            ! is_string(
                $value
            )
        ) {
            return $value;
        }

        $value =
            trim(
                $value
            );

        return $value === ''
            ? null
            : $value;
    }
}