<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class RoleLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize login input before validation.
     */
    protected function prepareForValidation(): void
    {
        $email =
            $this->input(
                'email'
            );

        if (is_string($email)) {
            $this->merge([
                'email' =>
                    strtolower(
                        trim(
                            $email
                        )
                    ),
            ]);
        }

        $deviceName =
            $this->input(
                'device_name'
            );

        if (is_string($deviceName)) {
            $deviceName =
                trim(
                    $deviceName
                );

            $this->merge([
                'device_name' =>
                    $deviceName === ''
                        ? null
                        : $deviceName,
            ]);
        }
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
                'max:255',
            ],

            'device_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }
}