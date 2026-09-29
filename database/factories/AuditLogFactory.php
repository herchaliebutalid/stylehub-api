<?php

namespace Database\Factories;

use App\Enums\AuditEvent;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_id' =>
                User::factory()
                    ->admin(),

            'actor_role' =>
                UserRole::Admin
                    ->value,

            'event' =>
                AuditEvent::ShopApproved,

            'auditable_type' =>
                null,

            'auditable_id' =>
                null,

            'ip_address' =>
                '127.0.0.1',

            'user_agent' =>
                'StyleHub Automated Test',

            'metadata' => [
                'source' =>
                    'factory',
            ],

            'created_at' =>
                now(),
        ];
    }
}