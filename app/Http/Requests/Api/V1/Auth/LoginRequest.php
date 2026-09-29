<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class LoginRequest extends FormRequest
{
    /**
     * Anyone may attempt to authenticate.
     *
     * Rate limiting is applied at the route level.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize login data before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->input('email'))
                ? mb_strtolower(trim($this->input('email')))
                : $this->input('email'),

            'device_name' => is_string($this->input('device_name'))
                ? trim($this->input('device_name'))
                : $this->input('device_name'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
            ],

            'password' => [
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
}