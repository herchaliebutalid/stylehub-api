<?php

namespace App\Http\Requests\Api\V1\ShopOwner;

use App\Enums\ShopType;
use App\Support\PhilippinePhoneNumber;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

#[FailOnUnknownFields]
class UpdateShopProfileRequest extends FormRequest
{
    /**
     * Authorization is enforced by the shop-owner route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize user-entered shop data before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' =>
                $this->cleanString(
                    $this->input('name')
                ),

            'type' =>
                $this->cleanString(
                    $this->input('type')
                ),

            'description' =>
                $this->cleanNullableString(
                    $this->input('description')
                ),

            'phone_number' =>
                is_string(
                    $this->input('phone_number')
                )
                    ? PhilippinePhoneNumber::normalize(
                        $this->input('phone_number')
                    )
                    : $this->input('phone_number'),

            'email' =>
                $this->normalizeEmail(
                    $this->input('email')
                ),

            'address' =>
                $this->cleanString(
                    $this->input('address')
                ),

            'city' =>
                $this->cleanString(
                    $this->input('city')
                ),

            'province' =>
                $this->cleanString(
                    $this->input('province')
                ),
        ]);
    }

    /**
     * Validate the complete editable shop profile.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:150',
            ],

            'type' => [
                'required',
                new Enum(
                    ShopType::class
                ),
            ],

            'description' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'phone_number' => [
                'required',
                'string',
                'max:20',
                'regex:/^\+639\d{9}$/',
            ],

            'email' => [
                'nullable',
                'string',
                'email:rfc',
                'max:255',
            ],

            'address' => [
                'required',
                'string',
                'min:5',
                'max:255',
            ],

            'city' => [
                'required',
                'string',
                'min:2',
                'max:100',
            ],

            'province' => [
                'required',
                'string',
                'min:2',
                'max:100',
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
                'The shop phone number must be a valid Philippine mobile number.',
        ];
    }

    private function cleanString(
        mixed $value
    ): mixed {
        return is_string($value)
            ? trim($value)
            : $value;
    }

    private function cleanNullableString(
        mixed $value
    ): mixed {
        if (! is_string($value)) {
            return $value;
        }

        $cleaned = trim($value);

        return $cleaned === ''
            ? null
            : $cleaned;
    }

    private function normalizeEmail(
        mixed $value
    ): mixed {
        if (! is_string($value)) {
            return $value;
        }

        $cleaned = mb_strtolower(
            trim($value)
        );

        return $cleaned === ''
            ? null
            : $cleaned;
    }
}