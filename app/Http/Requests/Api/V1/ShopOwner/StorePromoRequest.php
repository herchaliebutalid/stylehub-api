<?php

namespace App\Http\Requests\Api\V1\ShopOwner;

use App\Enums\PromoDiscountType;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

#[FailOnUnknownFields]
class StorePromoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize ordinary promotion fields while preserving
     * unknown fields so FailOnUnknownFields can reject them.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (
            [
                'title',
                'discount_type',
                'starts_at',
                'ends_at',
            ] as $field
        ) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = $this->input($field);

            $normalized[$field] =
                is_string($value)
                    ? trim($value)
                    : $value;
        }

        if ($this->exists('description')) {
            $description =
                $this->input('description');

            if (is_string($description)) {
                $description =
                    trim($description);

                $normalized['description'] =
                    $description === ''
                        ? null
                        : $description;
            } else {
                $normalized['description'] =
                    $description;
            }
        }

        if ($this->exists('discount_value')) {
            $value =
                $this->input('discount_value');

            $normalized['discount_value'] =
                is_string($value)
                    ? trim($value)
                    : $value;
        }

        if ($normalized !== []) {
            $this->merge(
                $normalized
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | Target
            |--------------------------------------------------------------------------
            |
            | null = shop-wide promotion.
            |
            | A supplied service ID is later verified against the
            | authenticated owner's own shop.
            |
            */

            'service_id' => [
                'present',
                'nullable',
                'integer',
                'min:1',
            ],

            /*
            |--------------------------------------------------------------------------
            | Promotion Information
            |--------------------------------------------------------------------------
            */

            'title' => [
                'required',
                'string',
                'min:2',
                'max:150',
            ],

            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],

            /*
            |--------------------------------------------------------------------------
            | Discount
            |--------------------------------------------------------------------------
            */

            'discount_type' => [
                'required',
                new Enum(
                    PromoDiscountType::class
                ),
            ],

            'discount_value' => [
                'required',
                'numeric',
                'gt:0',
                'max:99999999.99',
                'regex:/^\d{1,8}(\.\d{1,2})?$/',
            ],

            /*
            |--------------------------------------------------------------------------
            | Promotion Period
            |--------------------------------------------------------------------------
            */

            'starts_at' => [
                'required',
                'date_format:Y-m-d H:i:s',
            ],

            'ends_at' => [
                'required',
                'date_format:Y-m-d H:i:s',
                'after:starts_at',
            ],

            /*
            |--------------------------------------------------------------------------
            | Manual State
            |--------------------------------------------------------------------------
            */

            'is_active' => [
                'required',
                'boolean',
            ],
        ];
    }

    /**
     * Percentage promotions cannot exceed 100%.
     */
    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(
            function (
                Validator $validator
            ): void {
                $type =
                    $this->input(
                        'discount_type'
                    );

                $value =
                    $this->input(
                        'discount_value'
                    );

                if (
                    $type
                    !== PromoDiscountType::Percentage
                        ->value
                    || ! is_numeric($value)
                ) {
                    return;
                }

                if ((float) $value > 100) {
                    $validator
                        ->errors()
                        ->add(
                            'discount_value',
                            'A percentage discount cannot exceed 100%.'
                        );
                }
            }
        );
    }
}