<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\AuditResourceType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\AuditLog
 */
class AdminAuditLogResource extends JsonResource
{
    /**
     * Administrator-safe representation of one audit event.
     *
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        $resourceType =
            AuditResourceType::fromStoredMorph(
                $this->auditable_type
            );

        return [
            'id' =>
                $this->id,

            /*
            |--------------------------------------------------------------------------
            | Event
            |--------------------------------------------------------------------------
            */

            'event' => [
                'value' =>
                    $this->event
                        ->value,

                'label' =>
                    $this->event
                        ->label(),
            ],

            /*
            |--------------------------------------------------------------------------
            | Actor
            |--------------------------------------------------------------------------
            |
            | actor_role is the historical snapshot.
            |
            | The related user could later be soft/hard deleted,
            | but the snapshot remains useful.
            |
            */

            'actor' => [
                'id' =>
                    $this->actor_id,

                'role' =>
                    $this->actor_role,
            ],

            /*
            |--------------------------------------------------------------------------
            | Affected Resource
            |--------------------------------------------------------------------------
            |
            | Never expose raw App\Models\... morph values.
            |
            */

            'resource' => [
                'type' =>
                    $resourceType?->value
                    ?? 'unknown',

                'type_label' =>
                    $resourceType?->label()
                    ?? 'Unknown',

                'id' =>
                    $this->auditable_id,
            ],

            /*
            |--------------------------------------------------------------------------
            | Safe Metadata
            |--------------------------------------------------------------------------
            */

            'metadata' =>
                $this->metadata
                ?? [],

            /*
            |--------------------------------------------------------------------------
            | Security Context
            |--------------------------------------------------------------------------
            |
            | Administrator-only.
            |
            */

            'request_context' => [
                'ip_address' =>
                    $this->ip_address,

                'user_agent' =>
                    $this->user_agent,
            ],

            'created_at' =>
                $this->created_at
                    ?->toISOString(),
        ];
    }
}