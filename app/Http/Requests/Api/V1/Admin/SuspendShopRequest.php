<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class SuspendShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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
                'min:10',
                'max:1000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' =>
                'A suspension reason is required.',

            'reason.min' =>
                'The suspension reason must contain at least 10 characters.',

            'reason.max' =>
                'The suspension reason may not exceed 1000 characters.',
        ];
    }
}