<?php

namespace App\Http\Requests\Api\V1\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

#[FailOnUnknownFields]
class AdminReportOverviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => [
                'sometimes',
                'date_format:Y-m-d',
            ],
            'date_to' => [
                'sometimes',
                'date_format:Y-m-d',
                'after_or_equal:date_from',
            ],
            'format' => [
                'sometimes',
                Rule::in(['json', 'pdf', 'latest']),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->query()) as $field) {
                if (!in_array(
                    $field,
                    ['date_from', 'date_to', 'format'],
                    true
                )) {
                    $validator->errors()->add(
                        $field,
                        "The {$field} query parameter is not allowed."
                    );
                }
            }

            $from = $this->input('date_from');
            $to = $this->input('date_to');

            if (!is_string($from) || !is_string($to)) {
                return;
            }

            try {
                $start = CarbonImmutable::createFromFormat(
                    '!Y-m-d',
                    $from
                )->startOfDay();

                $end = CarbonImmutable::createFromFormat(
                    '!Y-m-d',
                    $to
                )->startOfDay();
            } catch (\Throwable) {
                return;
            }

            if ($end->lessThan($start)) {
                return;
            }

            if ($start->diffInDays($end) > 366) {
                $validator->errors()->add(
                    'date_to',
                    'The reporting period may not exceed 366 days.'
                );
            }
        });
    }
}