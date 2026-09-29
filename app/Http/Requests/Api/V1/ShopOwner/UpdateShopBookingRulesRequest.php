<?php

namespace App\Http\Requests\Api\V1\ShopOwner;

use App\Models\ShopBookingRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class UpdateShopBookingRulesRequest extends FormRequest
{
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
            'slot_interval_minutes' => [
                'required',
                'integer',
                Rule::in([
                    5,
                    10,
                    15,
                    20,
                    30,
                    60,
                ]),
            ],

            'minimum_lead_time_minutes' => [
                'required',
                'integer',
                'min:'.ShopBookingRule::MIN_LEAD_TIME_MINUTES,
                'max:'.ShopBookingRule::MAX_LEAD_TIME_MINUTES,
            ],

            'booking_window_days' => [
                'required',
                'integer',
                'min:'.ShopBookingRule::MIN_BOOKING_WINDOW_DAYS,
                'max:'.ShopBookingRule::MAX_BOOKING_WINDOW_DAYS,
            ],

            'cancellation_cutoff_minutes' => [
                'required',
                'integer',
                'min:'.ShopBookingRule::MIN_CUTOFF_MINUTES,
                'max:'.ShopBookingRule::MAX_CUTOFF_MINUTES,
            ],

            'reschedule_cutoff_minutes' => [
                'required',
                'integer',
                'min:'.ShopBookingRule::MIN_CUTOFF_MINUTES,
                'max:'.ShopBookingRule::MAX_CUTOFF_MINUTES,
            ],
        ];
    }
}