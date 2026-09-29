<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class StoreRescheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $startsAt =
            $this->input(
                'requested_starts_at'
            );

        $reason =
            $this->input(
                'reason'
            );

        $this->merge([
            'requested_starts_at' =>
                is_string($startsAt)
                    ? trim($startsAt)
                    : $startsAt,

            'reason' =>
                $this->cleanNullableString(
                    $reason
                ),
        ]);
    }

    public function rules(): array
    {
        return [
            'requested_date' => [
                'required',
                'date_format:Y-m-d',
            ],

            'requested_starts_at' => [
                'required',
                'date_format:H:i',
            ],

            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    private function cleanNullableString(
        mixed $value
    ): mixed {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }
}