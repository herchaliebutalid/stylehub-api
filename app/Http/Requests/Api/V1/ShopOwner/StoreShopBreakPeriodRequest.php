<?php

namespace App\Http\Requests\Api\V1\ShopOwner;

use App\Enums\DayOfWeek;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

#[FailOnUnknownFields]
class StoreShopBreakPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (
            [
                'starts_at',
                'ends_at',
                'label',
            ] as $field
        ) {
            if (! is_string($this->input($field))) {
                continue;
            }

            $value = trim(
                $this->input($field)
            );

            $this->merge([
                $field =>
                    $field === 'label' && $value === ''
                        ? null
                        : $value,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'day_of_week' => [
                'required',
                'integer',
                new Enum(DayOfWeek::class),
            ],

            'starts_at' => [
                'required',
                'date_format:H:i',
            ],

            'ends_at' => [
                'required',
                'date_format:H:i',
            ],

            'label' => [
                'nullable',
                'string',
                'max:120',
            ],
        ];
    }

    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(
            function (Validator $validator): void {
                $startsAt = $this->input(
                    'starts_at'
                );

                $endsAt = $this->input(
                    'ends_at'
                );

                if (
                    ! is_string($startsAt)
                    || ! is_string($endsAt)
                    || preg_match('/^\d{2}:\d{2}$/', $startsAt) !== 1
                    || preg_match('/^\d{2}:\d{2}$/', $endsAt) !== 1
                ) {
                    return;
                }

                if ($endsAt <= $startsAt) {
                    $validator->errors()->add(
                        'ends_at',
                        'The break end time must be later than the start time.'
                    );
                }
            }
        );
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'starts_at.date_format' =>
                'The break start time must use the HH:MM format.',

            'ends_at.date_format' =>
                'The break end time must use the HH:MM format.',
        ];
    }
}