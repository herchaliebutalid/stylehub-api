<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class VerifyPhoneNumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (
            is_string(
                $this->input(
                    'code'
                )
            )
        ) {
            $this->merge([
                'code' =>
                    trim(
                        $this->input(
                            'code'
                        )
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
            'code' => [
                'required',
                'string',
                'regex:/^\d{6}$/',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' =>
                'The verification code must contain exactly 6 digits.',
        ];
    }
}