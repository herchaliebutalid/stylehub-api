<?php

namespace App\Http\Requests\Api\V1\ShopOwner;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

#[FailOnUnknownFields]
class GetShopOwnerAnalyticsRequest extends FormRequest
{
    private const MAX_RANGE_DAYS = 366;

    /**
     * @var list<string>
     */
    private const ALLOWED_QUERY_FIELDS = [
        'date_from',
        'date_to',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_from' => [
                'required_with:date_to',
                'date_format:Y-m-d',
            ],

            'date_to' => [
                'required_with:date_from',
                'date_format:Y-m-d',
                'after_or_equal:date_from',
            ],
        ];
    }

    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(
            function (
                Validator $validator
            ): void {
                foreach (
                    array_keys(
                        $this->query()
                    ) as $field
                ) {
                    if (
                        in_array(
                            $field,
                            self::ALLOWED_QUERY_FIELDS,
                            true
                        )
                    ) {
                        continue;
                    }

                    $validator
                        ->errors()
                        ->add(
                            $field,
                            "The {$field} query parameter is not allowed."
                        );
                }

                $dateFrom =
                    $this->input(
                        'date_from'
                    );

                $dateTo =
                    $this->input(
                        'date_to'
                    );

                if (
                    ! is_string(
                        $dateFrom
                    )
                    || ! is_string(
                        $dateTo
                    )
                ) {
                    return;
                }

                try {
                    $from =
                        CarbonImmutable::createFromFormat(
                            'Y-m-d',
                            $dateFrom
                        )
                            ->startOfDay();

                    $to =
                        CarbonImmutable::createFromFormat(
                            'Y-m-d',
                            $dateTo
                        )
                            ->startOfDay();
                } catch (\Throwable) {
                    return;
                }

                if (
                    $to->lessThan(
                        $from
                    )
                ) {
                    return;
                }

                if (
                    $from->diffInDays(
                        $to
                    )
                    > self::MAX_RANGE_DAYS
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'date_to',
                            'The analytics period may not exceed 366 days.'
                        );
                }
            }
        );
    }
}