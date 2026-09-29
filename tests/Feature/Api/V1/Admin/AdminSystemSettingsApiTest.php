<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\AuditEvent;
use App\Enums\AuditResourceType;
use App\Enums\SmsNotificationType;
use App\Models\AuditLog;
use App\Models\SmsNotification;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Services\Sms\SmsTemplateRenderer;
use App\Support\Sms\SmsTemplateCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSystemSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_settings_require_authentication(): void
    {
        $this->getJson(
            '/api/v1/admin/system-settings'
        )->assertUnauthorized();

        $this->putJson(
            '/api/v1/admin/system-settings/sms-templates/appointment_confirmed',
            [
                'template' =>
                    'Confirmed at {shop_name}.',
            ]
        )->assertUnauthorized();
    }

    public function test_customer_and_shop_owner_cannot_access_system_settings(): void
    {
        foreach (
            [
                User::factory()
                    ->customer()
                    ->create(),

                User::factory()
                    ->shopOwner()
                    ->create(),
            ] as $user
        ) {
            $this->actingAs(
                $user,
                'sanctum'
            )
                ->getJson(
                    '/api/v1/admin/system-settings'
                )
                ->assertForbidden();
        }
    }

    public function test_suspended_admin_cannot_access_system_settings(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->suspended()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/system-settings'
            )
            ->assertForbidden();
    }

    public function test_admin_receives_safe_sms_configuration_and_only_editable_templates(): void
    {
        config([
            'sms.enabled' =>
                true,

            'sms.provider' =>
                'semaphore',

            'sms.live_test.enabled' =>
                false,

            'sms.semaphore.api_key' =>
                'super-secret-api-key',

            'sms.semaphore.endpoint' =>
                'https://secret-provider.example/messages',

            'sms.semaphore.sender_name' =>
                'SECRET_SENDER_NAME',

            'sms.reminders.lead_minutes' =>
                1440,

            'sms.reminders.minimum_lead_minutes' =>
                60,
        ]);

        $admin =
            User::factory()
                ->admin()
                ->create();

        $response =
            $this->actingAs(
                $admin,
                'sanctum'
            )
                ->getJson(
                    '/api/v1/admin/system-settings'
                )
                ->assertOk()
                ->assertJsonPath(
                    'data.sms.configuration.enabled',
                    true
                )
                ->assertJsonPath(
                    'data.sms.configuration.provider',
                    'semaphore'
                )
                ->assertJsonPath(
                    'data.sms.configuration.sender_name_configured',
                    true
                )
                ->assertJsonPath(
                    'data.sms.configuration.live_test_enabled',
                    false
                )
                ->assertJsonPath(
                    'data.sms.configuration.reminders.lead_minutes',
                    1440
                )
                ->assertJsonPath(
                    'data.sms.configuration.reminders.minimum_lead_minutes',
                    60
                )
                ->assertJsonCount(
                    7,
                    'data.sms.templates'
                )
                ->assertJsonMissing([
                    'type' =>
                        SmsNotificationType::SystemTest
                            ->value,
                ]);

        $content =
            $response->getContent();

        $this->assertStringNotContainsString(
            'super-secret-api-key',
            $content
        );

        $this->assertStringNotContainsString(
            'https://secret-provider.example/messages',
            $content
        );

        $this->assertStringNotContainsString(
            'SECRET_SENDER_NAME',
            $content
        );

        $this->assertStringNotContainsString(
            'api_key',
            $content
        );

        $this->assertStringNotContainsString(
            'endpoint',
            $content
        );
    }

    public function test_template_payload_exposes_only_safe_editor_metadata_and_placeholder_rules(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/system-settings'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.sms.templates.0.type',
                SmsNotificationType::BookingReceived
                    ->value
            )
            ->assertJsonPath(
                'data.sms.templates.0.label',
                'Booking Received'
            )
            ->assertJsonPath(
                'data.sms.templates.0.max_characters',
                SmsTemplateCatalog::MAX_TEMPLATE_CHARACTERS
            )
            ->assertJsonPath(
                'data.sms.templates.0.allowed_placeholders.0',
                '{shop_name}'
            )
            ->assertJsonMissingPath(
                'data.sms.templates.0.updated_by.password'
            );
    }

    public function test_admin_can_update_editable_template_and_future_rendering_uses_it(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $newTemplate =
            'StyleHub confirmed: {shop_name} on {appointment_date} at {appointment_time}.';

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->putJson(
                '/api/v1/admin/system-settings/sms-templates/appointment_confirmed',
                [
                    'template' =>
                        $newTemplate,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.template.type',
                'appointment_confirmed'
            )
            ->assertJsonPath(
                'data.template.template',
                $newTemplate
            )
            ->assertJsonPath(
                'data.template.updated_by_admin_id',
                $admin->id
            );

        $message =
            app(
                SmsTemplateRenderer::class
            )->render(
                SmsNotificationType::AppointmentConfirmed,
                [
                    'shop_name' =>
                        'StyleHub Studio',

                    'appointment_date' =>
                        'Sep 20, 2026',

                    'appointment_time' =>
                        '10:00 AM',
                ]
            );

        $this->assertSame(
            'StyleHub confirmed: StyleHub Studio on Sep 20, 2026 at 10:00 AM.',
            $message
        );
    }

    public function test_unsupported_or_malformed_placeholders_are_rejected_without_changing_template(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $original =
            SmsTemplate::query()
                ->where(
                    'type',
                    'appointment_confirmed'
                )
                ->firstOrFail()
                ->template;

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->putJson(
                '/api/v1/admin/system-settings/sms-templates/appointment_confirmed',
                [
                    'template' =>
                        'Confirmed: {shop_name} {service_name}',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'template',
            ]);

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->putJson(
                '/api/v1/admin/system-settings/sms-templates/appointment_confirmed',
                [
                    'template' =>
                        'Confirmed: {shop-name}',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'template',
            ]);

        $this->assertSame(
            $original,
            SmsTemplate::query()
                ->where(
                    'type',
                    'appointment_confirmed'
                )
                ->value(
                    'template'
                )
        );

        $this->assertDatabaseMissing(
            'audit_logs',
            [
                'event' =>
                    AuditEvent::SmsTemplateUpdated
                        ->value,
            ]
        );
    }

    public function test_system_test_and_unknown_sms_types_cannot_be_edited(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $payload = [
            'template' =>
                'This should never become editable.',
        ];

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->putJson(
                '/api/v1/admin/system-settings/sms-templates/system_test',
                $payload
            )
            ->assertNotFound();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->putJson(
                '/api/v1/admin/system-settings/sms-templates/not_a_real_type',
                $payload
            )
            ->assertNotFound();
    }

    public function test_admin_cannot_inject_template_type_editor_or_provider_configuration(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->putJson(
                '/api/v1/admin/system-settings/sms-templates/appointment_reminder',
                [
                    'template' =>
                        'Reminder at {shop_name}.',

                    'type' =>
                        'system_test',

                    'updated_by' =>
                        999,

                    'provider' =>
                        'other-provider',

                    'api_key' =>
                        'client-controlled-secret',
                ]
            )
            ->assertUnprocessable();

        $this->assertDatabaseHas(
            'sms_templates',
            [
                'type' =>
                    'appointment_reminder',

                'updated_by' =>
                    null,
            ]
        );
    }

    public function test_template_update_is_audited_without_storing_message_content(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $newTemplate =
            'Reminder: {shop_name} on {appointment_date} at {appointment_time}.';

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->putJson(
                '/api/v1/admin/system-settings/sms-templates/appointment_reminder',
                [
                    'template' =>
                        $newTemplate,
                ]
            )
            ->assertOk();

        $log =
            AuditLog::query()
                ->where(
                    'event',
                    AuditEvent::SmsTemplateUpdated
                        ->value
                )
                ->firstOrFail();

        $template =
            SmsTemplate::query()
                ->where(
                    'type',
                    'appointment_reminder'
                )
                ->firstOrFail();

        $this->assertSame(
            $admin->id,
            $log->actor_id
        );

        $this->assertSame(
            $template->id,
            $log->auditable_id
        );

        $this->assertSame(
            AuditResourceType::SmsTemplate,
            AuditResourceType::fromStoredMorph(
                $log->auditable_type
            )
        );

        $this->assertSame(
            'admin_system_settings',
            $log->metadata['source']
        );

        $this->assertSame(
            'appointment_reminder',
            $log->metadata['sms_type']
        );

        $metadataJson =
            json_encode(
                $log->metadata,
                JSON_THROW_ON_ERROR
            );

        $this->assertStringNotContainsString(
            $newTemplate,
            $metadataJson
        );

        $this->assertArrayNotHasKey(
            'template',
            $log->metadata
        );

        $this->assertArrayNotHasKey(
            'message',
            $log->metadata
        );
    }

    public function test_existing_queued_sms_message_snapshot_is_not_changed_by_template_edit(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $notification =
            SmsNotification::factory()
                ->create([
                    'type' =>
                        SmsNotificationType::AppointmentConfirmed,

                    'message' =>
                        'Original queued SMS snapshot.',
                ]);

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->putJson(
                '/api/v1/admin/system-settings/sms-templates/appointment_confirmed',
                [
                    'template' =>
                        'New future message for {shop_name}.',
                ]
            )
            ->assertOk();

        $this->assertSame(
            'Original queued SMS snapshot.',
            $notification
                ->fresh()
                ->message
        );
    }

    public function test_new_audit_event_and_resource_type_can_be_filtered_through_existing_audit_api(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->putJson(
                '/api/v1/admin/system-settings/sms-templates/booking_received',
                [
                    'template' =>
                        'Booking at {shop_name}: {service_name} on {appointment_date} at {appointment_time}.',
                ]
            )
            ->assertOk();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/audit-logs?event=sms_template_updated&resource_type=sms_template'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.pagination.total',
                1
            )
            ->assertJsonPath(
                'data.audit_logs.0.event.value',
                'sms_template_updated'
            )
            ->assertJsonPath(
                'data.audit_logs.0.resource.type',
                'sms_template'
            );
    }
}