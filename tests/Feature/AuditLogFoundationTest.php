<?php

namespace Tests\Feature;

use App\Enums\AuditEvent;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Shop;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\AuditMetadataSanitizer;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class AuditLogFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_valid_audit_log(): void
    {
        $log =
            AuditLog::factory()
                ->create();

        $this->assertSame(
            AuditEvent::ShopApproved,
            $log->event
        );

        $this->assertSame(
            UserRole::Admin->value,
            $log->actor_role
        );

        $this->assertIsArray(
            $log->metadata
        );

        $this->assertNotNull(
            $log->created_at
        );
    }

    public function test_audit_log_belongs_to_actor(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $log =
            AuditLog::factory()
                ->create([
                    'actor_id' =>
                        $admin->id,

                    'actor_role' =>
                        UserRole::Admin
                            ->value,
                ]);

        $this->assertSame(
            $admin->id,
            $log->actor->id
        );
    }

    public function test_audit_log_can_reference_polymorphic_resource(): void
    {
        $shop =
            Shop::factory()
                ->approved()
                ->create();

        $log =
            AuditLog::factory()
                ->create([
                    'auditable_type' =>
                        $shop->getMorphClass(),

                    'auditable_id' =>
                        $shop->id,
                ]);

        $this->assertInstanceOf(
            Shop::class,
            $log->auditable
        );

        $this->assertSame(
            $shop->id,
            $log->auditable->id
        );
    }

    public function test_audit_log_is_fully_guarded_from_mass_assignment(): void
    {
        $this->expectException(
            MassAssignmentException::class
        );

        (
            new AuditLog()
        )->fill([
            'actor_id' =>
                999,

            'event' =>
                AuditEvent::ShopApproved
                    ->value,

            'metadata' => [
                'malicious' =>
                    true,
            ],
        ]);
    }

    public function test_audit_metadata_sanitizer_accepts_safe_nested_metadata(): void
    {
        $metadata =
            AuditMetadataSanitizer::sanitize([
                'previous_status' =>
                    'pending',

                'new_status' =>
                    'approved',

                'changes' => [
                    'availability_status' =>
                        'open',
                ],
            ]);

        $this->assertSame(
            'pending',
            $metadata[
                'previous_status'
            ]
        );

        $this->assertSame(
            'open',
            $metadata[
                'changes'
            ][
                'availability_status'
            ]
        );
    }

    public function test_audit_metadata_rejects_password_field(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        AuditMetadataSanitizer::sanitize([
            'password' =>
                'must-never-be-logged',
        ]);
    }

    public function test_audit_metadata_rejects_nested_api_key(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        AuditMetadataSanitizer::sanitize([
            'provider' => [
                'api_key' =>
                    'must-never-be-logged',
            ],
        ]);
    }

    public function test_audit_logger_derives_actor_and_subject_information(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $shop =
            Shop::factory()
                ->approved()
                ->create();

        $logger =
            app(
                AuditLogger::class
            );

        $log =
            $logger->record(
                actor:
                    $admin,

                event:
                    AuditEvent::ShopApproved,

                auditable:
                    $shop,

                metadata: [
                    'previous_status' =>
                        'pending',

                    'new_status' =>
                        'approved',
                ],
            );

        $this->assertSame(
            $admin->id,
            $log->actor_id
        );

        $this->assertSame(
            UserRole::Admin->value,
            $log->actor_role
        );

        $this->assertSame(
            AuditEvent::ShopApproved,
            $log->event
        );

        $this->assertSame(
            $shop->getMorphClass(),
            $log->auditable_type
        );

        $this->assertSame(
            $shop->id,
            $log->auditable_id
        );

        $this->assertSame(
            'approved',
            $log->metadata[
                'new_status'
            ]
        );
    }

    public function test_audit_log_cannot_be_updated_through_model(): void
    {
        $log =
            AuditLog::factory()
                ->create();

        $this->expectException(
            LogicException::class
        );

        $log->event =
            AuditEvent::ShopRejected;

        $log->save();
    }

    public function test_audit_log_cannot_be_deleted_through_model(): void
    {
        $log =
            AuditLog::factory()
                ->create();

        $this->expectException(
            LogicException::class
        );

        $log->delete();
    }
}