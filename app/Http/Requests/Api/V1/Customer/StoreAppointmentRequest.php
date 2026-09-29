<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class StoreAppointmentRequest extends FormRequest
{
    /**
     * Customer authorization is handled by middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize booking input.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'starts_at' => is_string(
                $this->input('starts_at')
            )
                ? trim($this->input('starts_at'))
                : $this->input('starts_at'),

            'notes' => $this->cleanNullableString(
                $this->input('notes')
            ),
        ]);
    }

    /**
     * Validate customer-supplied booking fields.
     *
     * Ownership, status, calculated end time, price and service snapshot
     * are deliberately not accepted from the client.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shop_id' => [
                'required',
                'integer',
                'min:1',
            ],

            'service_id' => [
                'required',
                'integer',
                'min:1',
            ],

            'appointment_date' => [
                'required',
                'date_format:Y-m-d',
            ],

            'starts_at' => [
                'required',
                'date_format:H:i',
            ],

            'notes' => [
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