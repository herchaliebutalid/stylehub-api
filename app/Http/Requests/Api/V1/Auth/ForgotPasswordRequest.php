<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        $role = $this->input('role');

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
        ];
    }
}