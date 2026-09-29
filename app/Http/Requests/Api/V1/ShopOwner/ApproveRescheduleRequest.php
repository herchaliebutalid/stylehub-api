<?php

namespace App\Http\Requests\Api\V1\ShopOwner;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class ApproveRescheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Approval accepts no client-controlled fields.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}