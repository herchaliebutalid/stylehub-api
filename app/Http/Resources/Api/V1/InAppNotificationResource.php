<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\InAppNotification
 */
class InAppNotificationResource extends JsonResource
{
    /**
     * Return only notification information safe for the mobile client.
     *
     * Internal recipient ownership and deduplication information are
     * intentionally excluded.
     *
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        return [
            'id' =>
                $this->id,

            'type' =>
                $this->type
                    ->value,

            'type_label' =>
                $this->type
                    ->label(),

            'title' =>
                $this->title,

            'message' =>
                $this->message,

            'is_read' =>
                $this->isRead(),

            'read_at' =>
                $this->read_at
                    ?->toISOString(),

            'created_at' =>
                $this->created_at
                    ?->toISOString(),

            /*
            |--------------------------------------------------------------------------
            | Safe Navigation Target
            |--------------------------------------------------------------------------
            |
            | The API does not expose arbitrary URLs or client-controlled routes.
            |
            | React Native will later decide the correct screen based on:
            |
            | - the authenticated user's role
            | - target.kind
            | - target.id
            |
            */

            'target' =>
                $this->navigationTarget(),
        ];
    }

    /**
     * Return the most specific navigation target available.
     *
     * @return array{kind: string, id: int}|null
     */
    private function navigationTarget(): ?array
    {
        if (
            $this->reschedule_request_id
            !== null
        ) {
            return [
                'kind' =>
                    'reschedule_request',

                'id' =>
                    (int) $this
                        ->reschedule_request_id,
            ];
        }

        if (
            $this->feedback_id
            !== null
        ) {
            return [
                'kind' =>
                    'feedback',

                'id' =>
                    (int) $this
                        ->feedback_id,
            ];
        }

        if (
            $this->promo_id
            !== null
        ) {
            return [
                'kind' =>
                    'promo',

                'id' =>
                    (int) $this
                        ->promo_id,
            ];
        }

        if (
            $this->appointment_id
            !== null
        ) {
            return [
                'kind' =>
                    'appointment',

                'id' =>
                    (int) $this
                        ->appointment_id,
            ];
        }

        return null;
    }
}