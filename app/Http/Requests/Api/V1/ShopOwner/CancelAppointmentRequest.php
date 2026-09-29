<?php

namespace App\Http\Requests\Api\V1\ShopOwner;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class CancelAppointmentRequest extends FormRequest
{
    /**
     * Authorization is handled by middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize the cancellation reason.
     */
    protected function prepareForValidation(): void
    {
        $reason = $this->input('reason');

        $this->merge([
            'reason' => is_string($reason)
                ? trim($reason)
                : $reason,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'min:5',
                'max:1000',
            ],
        ];
    }
}