<?php

namespace App\Http\Requests\Api\V1\ShopOwner;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class UpdateShopLocationRequest extends FormRequest
{
    /**
     * Authorization is enforced by the shop-owner route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize string coordinates before validation.
     */
    protected function prepareForValidation(): void
    {
        $latitude = $this->input('latitude');
        $longitude = $this->input('longitude');

        $this->merge([
            'latitude' => is_string($latitude)
                ? trim($latitude)
                : $latitude,

            'longitude' => is_string($longitude)
                ? trim($longitude)
                : $longitude,
        ]);
    }

    /**
     * Validate a complete WGS84 latitude/longitude pair.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'latitude' => [
                'required',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'required',
                'numeric',
                'between:-180,180',
            ],
        ];
    }
}