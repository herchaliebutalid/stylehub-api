<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class RejectShopRequest extends FormRequest
{
    /**
     * Role authorization is handled by the admin middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize the rejection reason.
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
     * Validate rejection information.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'min:10',
                'max:1000',
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
            'reason.required' =>
                'A rejection reason is required.',

            'reason.min' =>
                'The rejection reason must contain at least 10 characters.',

            'reason.max' =>
                'The rejection reason may not exceed 1000 characters.',
        ];
    }
}