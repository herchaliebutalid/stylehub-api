<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class ReactivateShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reactivation intentionally accepts no client-controlled fields.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}