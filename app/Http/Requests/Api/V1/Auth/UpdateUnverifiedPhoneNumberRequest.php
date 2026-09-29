<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Models\User;
use App\Support\PhilippinePhoneNumber;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class UpdateUnverifiedPhoneNumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && (
                $user->isCustomer()
                || $user->isShopOwner()
            );
    }

    protected function prepareForValidation(): void
    {
        if (
            is_string(
                $this->input(
                    'phone_number'
                )
            )
        ) {
            $this->merge([
                'phone_number' =>
                    PhilippinePhoneNumber::normalize(
                        $this->input(
                            'phone_number'
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

            'current_password' => [
                'required',
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
        ];
    }
}