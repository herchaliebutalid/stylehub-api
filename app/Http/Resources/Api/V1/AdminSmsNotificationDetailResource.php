<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class AdminSmsNotificationDetailResource extends AdminSmsNotificationResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        $base =
            parent::toArray(
                $request
            );

        /*
        |--------------------------------------------------------------------------
        | Administrator-only Diagnostic Detail
        |--------------------------------------------------------------------------
        */

        $base['recipient'] =
            $this->recipient;

        $base['message'] =
            $this->message;

        $base['provider_message_id'] =
            $this->provider_message_id;

        $base['dedupe_key'] =
            $this->dedupe_key;

        return $base;
    }
}