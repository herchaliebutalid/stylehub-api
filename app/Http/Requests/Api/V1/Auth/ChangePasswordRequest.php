<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

#[FailOnUnknownFields]
class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => [
                'required',
                'string',
                'max:255',
            ],

            'password' => [
                'required',
                'string',
                'confirmed',
                'different:current_password',
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
            'password.confirmed' =>
                'The password confirmation does not match.',

            'password.different' =>
                'The new password must be different from your current password.',
        ];
    }
}