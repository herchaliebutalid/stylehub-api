<?php

namespace App\Http\Requests\Api\V1\ShopOwner;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class UpdateShopBlockedDateRequest extends FormRequest
{
    /**
     * Authorization is enforced by the shop-owner route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize owner-entered closure data before validation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('blocked_date'))) {
            $this->merge([
                'blocked_date' => trim(
                    $this->input('blocked_date')
                ),
            ]);
        }

        if (is_string($this->input('reason'))) {
            $reason = trim(
                $this->input('reason')
            );

            $this->merge([
                'reason' => $reason === ''
                    ? null
                    : $reason,
            ]);
        }
    }

    /**
     * PUT replaces the complete editable blocked-date record.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'blocked_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ],

            'reason' => [
                'nullable',
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
            'blocked_date.after_or_equal' =>
                'The blocked date cannot be in the past.',
        ];
    }
}