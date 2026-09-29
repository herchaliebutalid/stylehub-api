<?php

namespace App\Http\Requests\Api\V1\ShopOwner;

use App\Enums\ShopAvailabilityStatus;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

#[FailOnUnknownFields]
class UpdateShopAvailabilityRequest extends FormRequest
{
    /**
     * Authorization is handled by middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize the availability status.
     */
    protected function prepareForValidation(): void
    {
        $status = $this->input('status');

        $this->merge([
            'status' => is_string($status)
                ? trim($status)
                : $status,
        ]);
    }

    /**
     * Validate the requested availability state.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                new Enum(
                    ShopAvailabilityStatus::class
                ),
            ],
        ];
    }
}