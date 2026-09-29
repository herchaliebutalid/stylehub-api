<?php

namespace App\Http\Requests\Api\V1\Notifications;

use Illuminate\Foundation\Http\FormRequest;

class NotificationAccessRequest extends FormRequest
{
    /**
     * Notifications are an account-level mobile feature.
     *
     * Customers and shop owners must have a verified phone number.
     * Administrators are intentionally excluded from this mobile API.
     */
    public function authorize(): bool
    {
        $user =
            $this->user();

        if ($user === null) {
            return false;
        }

        if (
            ! $user->isCustomer()
            &&
            ! $user->isShopOwner()
        ) {
            return false;
        }

        return $user
            ->hasVerifiedPhoneNumber();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}