<?php

namespace App\Http\Requests\Api\V1\ShopOwner;

use App\Enums\DayOfWeek;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

#[FailOnUnknownFields]
class UpdateShopScheduleRequest extends FormRequest
{
    /**
     * Authorization is handled by the authenticated,
     * active, approved-shop-owner middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize operating-time values without removing
     * unknown fields before validation.
     */
    protected function prepareForValidation(): void
    {
        $schedule = $this->input('schedule');

        if (! is_array($schedule)) {
            return;
        }

        $normalizedSchedule = array_map(
            function (mixed $entry): mixed {
                if (! is_array($entry)) {
                    return $entry;
                }

                $normalized = $entry;

                if (array_key_exists('opens_at', $entry)) {
                    $normalized['opens_at'] =
                        $this->cleanNullableString(
                            $entry['opens_at']
                        );
                }

                if (array_key_exists('closes_at', $entry)) {
                    $normalized['closes_at'] =
                        $this->cleanNullableString(
                            $entry['closes_at']
                        );
                }

                return $normalized;
            },
            $schedule
        );

        $this->merge([
            'schedule' => $normalizedSchedule,
        ]);
    }

    /**
     * Validate a complete seven-day weekly schedule.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | Complete Weekly Schedule
            |--------------------------------------------------------------------------
            |
            | Exactly seven entries are required.
            |
            | Combined with:
            | - day_of_week enum
            | - distinct
            |
            | this guarantees Monday through Sunday appear exactly once.
            |
            */

            'schedule' => [
                'required',
                'array',
                'size:7',
            ],

            'schedule.*' => [
                'required',
                'array:day_of_week,is_open,opens_at,closes_at',
            ],

            'schedule.*.day_of_week' => [
                'required',
                'integer',
                new Enum(DayOfWeek::class),
                'distinct',
            ],

            'schedule.*.is_open' => [
                'required',
                'boolean',
            ],

            'schedule.*.opens_at' => [
                'nullable',
                'date_format:H:i',
            ],

            'schedule.*.closes_at' => [
                'nullable',
                'date_format:H:i',
            ],
        ];
    }

    /**
     * Apply cross-field business-hour rules.
     */
    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(
            function (Validator $validator): void {
                $schedule = $this->input(
                    'schedule',
                    []
                );

                if (! is_array($schedule)) {
                    return;
                }

                foreach ($schedule as $index => $entry) {
                    if (! is_array($entry)) {
                        continue;
                    }

                    if (
                        ! array_key_exists(
                            'is_open',
                            $entry
                        )
                    ) {
                        continue;
                    }

                    $isOpen = filter_var(
                        $entry['is_open'],
                        FILTER_VALIDATE_BOOLEAN,
                        FILTER_NULL_ON_FAILURE
                    );

                    if ($isOpen === null) {
                        continue;
                    }

                    $opensAt =
                        $entry['opens_at'] ?? null;

                    $closesAt =
                        $entry['closes_at'] ?? null;

                    if ($isOpen) {
                        if ($opensAt === null) {
                            $validator->errors()->add(
                                "schedule.{$index}.opens_at",
                                'An opening time is required for an open day.'
                            );
                        }

                        if ($closesAt === null) {
                            $validator->errors()->add(
                                "schedule.{$index}.closes_at",
                                'A closing time is required for an open day.'
                            );
                        }

                        if (
                            is_string($opensAt)
                            && is_string($closesAt)
                            && preg_match(
                                '/^\d{2}:\d{2}$/',
                                $opensAt
                            ) === 1
                            && preg_match(
                                '/^\d{2}:\d{2}$/',
                                $closesAt
                            ) === 1
                            && $closesAt <= $opensAt
                        ) {
                            $validator->errors()->add(
                                "schedule.{$index}.closes_at",
                                'The closing time must be later than the opening time.'
                            );
                        }

                        continue;
                    }

                    if ($opensAt !== null) {
                        $validator->errors()->add(
                            "schedule.{$index}.opens_at",
                            'A closed day cannot have an opening time.'
                        );
                    }

                    if ($closesAt !== null) {
                        $validator->errors()->add(
                            "schedule.{$index}.closes_at",
                            'A closed day cannot have a closing time.'
                        );
                    }
                }
            }
        );
    }

    /**
     * Human-friendly validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'schedule.size' =>
                'The weekly schedule must contain exactly seven days.',

            'schedule.*.day_of_week.distinct' =>
                'Each weekday may appear only once in the weekly schedule.',

            'schedule.*.opens_at.date_format' =>
                'The opening time must use the HH:MM format.',

            'schedule.*.closes_at.date_format' =>
                'The closing time must use the HH:MM format.',
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