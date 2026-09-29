<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Enums\ShopType;
use App\Models\User;
use App\Support\PhilippinePhoneNumber;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

#[FailOnUnknownFields]
class RegisterShopOwnerRequest extends FormRequest
{
    /**
     * Public shop-owner registration is allowed.
     *
     * The server determines the user's role and all shop approval fields.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize incoming account and shop data before validation.
     */
    protected function prepareForValidation(): void
    {
        $shop =
            $this->input(
                'shop'
            );

        $normalizedShop =
            $shop;

        if (
            is_array(
                $shop
            )
        ) {
            $normalizedShop =
                array_merge(
                    $shop,
                    [
                        'name' =>
                            $this->cleanString(
                                $shop['name']
                                ?? null
                            ),

                        'type' =>
                            $shop['type']
                            ?? null,

                        'description' =>
                            $this->cleanNullableString(
                                $shop['description']
                                ?? null
                            ),

                        'phone_number' =>
                            is_string(
                                $shop[
                                    'phone_number'
                                ]
                                ?? null
                            )
                                ? PhilippinePhoneNumber::normalize(
                                    $shop[
                                        'phone_number'
                                    ]
                                )
                                : (
                                    $shop[
                                        'phone_number'
                                    ]
                                    ?? null
                                ),

                        'email' =>
                            is_string(
                                $shop['email']
                                ?? null
                            )
                                ? mb_strtolower(
                                    trim(
                                        $shop['email']
                                    )
                                )
                                : (
                                    $shop['email']
                                    ?? null
                                ),

                        'address' =>
                            $this->cleanString(
                                $shop['address']
                                ?? null
                            ),

                        'city' =>
                            $this->cleanString(
                                $shop['city']
                                ?? null
                            ),

                        'province' =>
                            $this->cleanString(
                                $shop['province']
                                ?? null
                            ),
                    ]
                );
        }

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

            'shop' =>
                $normalizedShop,
        ]);
    }

    /**
     * Validate shop-owner account and shop registration data.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | Shop Owner Account
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | Shop Information
            |--------------------------------------------------------------------------
            */

            'shop' => [
                'required',
                'array:name,type,description,phone_number,email,address,city,province',
            ],

            'shop.name' => [
                'required',
                'string',
                'min:2',
                'max:150',
            ],

            'shop.type' => [
                'required',
                new Enum(
                    ShopType::class
                ),
            ],

            'shop.description' => [
                'nullable',
                'string',
                'max:2000',
            ],

            /*
             * The business contact number may be the same as the owner's
             * verified account number, so it is not globally unique.
             */
            'shop.phone_number' => [
                'required',
                'string',
                'max:20',
                'regex:/^\+639\d{9}$/',
            ],

            'shop.email' => [
                'nullable',
                'string',
                'email:rfc',
                'max:255',
            ],

            'shop.address' => [
                'required',
                'string',
                'min:5',
                'max:255',
            ],

            'shop.city' => [
                'required',
                'string',
                'min:2',
                'max:100',
            ],

            'shop.province' => [
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
                'The account phone number must be a valid Philippine mobile number.',

            'phone_number.unique' =>
                'This phone number is already associated with another account.',

            'shop.phone_number.regex' =>
                'The shop phone number must be a valid Philippine mobile number.',

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