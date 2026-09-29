<?php

namespace Tests\Feature;

use App\Enums\SmsNotificationType;
use App\Models\SmsTemplate;
use App\Services\Sms\SmsTemplateRenderer;
use App\Support\Sms\SmsTemplateCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsTemplateFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_facing_sms_templates_are_seeded_with_current_defaults(): void
    {
        $this->assertDatabaseCount(
            'sms_templates',
            7
        );

        foreach (
            SmsTemplateCatalog::editableTypes()
            as $type
        ) {
            $this->assertDatabaseHas(
                'sms_templates',
                [
                    'type' =>
                        $type->value,

                    'template' =>
                        SmsTemplateCatalog::defaultTemplate(
                            $type
                        ),
                ]
            );
        }
    }

    public function test_controlled_system_test_sms_is_not_an_editable_template(): void
    {
        $this->assertFalse(
            SmsTemplateCatalog::isEditable(
                SmsNotificationType::SystemTest
            )
        );

        $this->assertDatabaseMissing(
            'sms_templates',
            [
                'type' =>
                    SmsNotificationType::SystemTest
                        ->value,
            ]
        );
    }

    public function test_sms_template_type_and_editor_cannot_be_mass_assigned(): void
    {
        $template =
            new SmsTemplate();

        $template->fill([
            'type' =>
                SmsNotificationType::SystemTest
                    ->value,

            'template' =>
                'Safe editable template.',

            'updated_by' =>
                999,
        ]);

        $this->assertNull(
            $template->type
        );

        $this->assertNull(
            $template->updated_by
        );

        $this->assertSame(
            'Safe editable template.',
            $template->template
        );
    }

    public function test_placeholder_rules_are_specific_to_each_sms_type(): void
    {
        $this->assertNull(
            SmsTemplateCatalog::validationError(
                SmsNotificationType::BookingReceived,
                'Booking: {shop_name} {service_name} {appointment_date} {appointment_time}'
            )
        );

        $this->assertNotNull(
            SmsTemplateCatalog::validationError(
                SmsNotificationType::AppointmentConfirmed,
                'Confirmed: {shop_name} {service_name}'
            )
        );

        $this->assertNotNull(
            SmsTemplateCatalog::validationError(
                SmsNotificationType::AppointmentConfirmed,
                'Confirmed: {shop-name}'
            )
        );
    }

    public function test_renderer_uses_saved_template_and_replaces_allowed_placeholders(): void
    {
        $template =
            SmsTemplate::query()
                ->where(
                    'type',
                    SmsNotificationType::AppointmentConfirmed
                        ->value
                )
                ->firstOrFail();

        $template->template =
            'Confirmed: {shop_name} / {appointment_date} / {appointment_time}';

        $template->save();

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
            'Confirmed: StyleHub Studio / Sep 20, 2026 / 10:00 AM',
            $message
        );
    }

    public function test_renderer_falls_back_to_application_default_if_template_row_is_missing(): void
    {
        SmsTemplate::query()
            ->where(
                'type',
                SmsNotificationType::AppointmentReminder
                    ->value
            )
            ->delete();

        $message =
            app(
                SmsTemplateRenderer::class
            )->render(
                SmsNotificationType::AppointmentReminder,
                [
                    'shop_name' =>
                        'Fallback Shop',

                    'appointment_date' =>
                        'Sep 20, 2026',

                    'appointment_time' =>
                        '10:00 AM',
                ]
            );

        $this->assertSame(
            'StyleHub reminder: Your confirmed appointment at Fallback Shop is on Sep 20, 2026 at 10:00 AM.',
            $message
        );
    }
}