<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

#[FailOnUnknownFields]
class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        $role = $this->input('role');
        $code = $this->input('code');

        if (is_string($email)) {
            $this->merge([
                'email' => mb_strtolower(
                    trim($email)
                ),
            ]);
        }

        if (is_string($role)) {
            $this->merge([
                'role' => trim($role),
            ]);
        }

        if (is_string($code)) {
            $this->merge([
                'code' => trim($code),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'role' => [
                'required',
                'string',
                Rule::in([
                    UserRole::Customer->value,
                    UserRole::ShopOwner->value,
                ]),
            ],

            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
            ],

            'code' => [
                'required',
                'string',
                'regex:/^\d{6}$/',
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
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' =>
                'The password reset code must contain exactly 6 digits.',

            'password.confirmed' =>
                'The password confirmation does not match.',
        ];
    }
}