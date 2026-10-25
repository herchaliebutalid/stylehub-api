<?php

namespace App\Services\Admin;

use App\Enums\AuditEvent;
use App\Enums\SmsNotificationType;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Sms\SmsTemplateCatalog;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class AdminSystemSettingsService
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    /**
     * Safe read-only operational SMS configuration.
     *
     * The TextBee API key, gateway device ID,
     * and provider endpoint are deliberately excluded
     * from this response.
     *
     * Only safe configuration indicators are returned.
     *
     * These indicators do not establish that the
     * Android phone is currently online or that
     * an SMS has been delivered.
     *
     * @return array<string, mixed>
     */
    public function smsConfiguration(): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | Normal SMS Master Switch
            |--------------------------------------------------------------------------
            */

            'enabled' =>
                (bool) config(
                    'sms.enabled',
                    false
                ),

            /*
            |--------------------------------------------------------------------------
            | Current SMS Provider
            |--------------------------------------------------------------------------
            */

            'provider' =>
                (string) config(
                    'sms.provider',
                    'textbee'
                ),

            /*
            |--------------------------------------------------------------------------
            | TextBee API Key
            |--------------------------------------------------------------------------
            |
            | Return only whether a key is configured.
            | Never return the actual API key.
            |
            */

            'api_key_configured' =>
                filled(
                    config(
                        'sms.textbee.api_key'
                    )
                ),

            /*
            |--------------------------------------------------------------------------
            | TextBee Android Gateway Device
            |--------------------------------------------------------------------------
            |
            | Return only whether a device ID is configured.
            | Do not expose the actual device ID.
            |
            */

            'device_id_configured' =>
                filled(
                    config(
                        'sms.textbee.device_id'
                    )
                ),

            /*
            |--------------------------------------------------------------------------
            | Controlled Live Test
            |--------------------------------------------------------------------------
            */

            'live_test_enabled' =>
                (bool) config(
                    'sms.live_test.enabled',
                    false
                ),

            /*
            |--------------------------------------------------------------------------
            | Appointment Reminder Configuration
            |--------------------------------------------------------------------------
            */

            'reminders' => [
                'lead_minutes' =>
                    (int) config(
                        'sms.reminders.lead_minutes',
                        1440
                    ),

                'minimum_lead_minutes' =>
                    (int) config(
                        'sms.reminders.minimum_lead_minutes',
                        60
                    ),
            ],
        ];
    }

    /**
     * Return all editable SMS templates in
     * stable catalog order.
     *
     * Missing rows are represented by an unsaved
     * model containing the safe application default.
     *
     * This keeps the Administrator screen usable
     * even if a template row was accidentally removed.
     *
     * @return EloquentCollection<int, SmsTemplate>
     */
    public function templates(): EloquentCollection
    {
        $stored =
            SmsTemplate::query()
                ->whereIn(
                    'type',
                    SmsTemplateCatalog::editableValues()
                )
                ->get()
                ->keyBy(
                    'type'
                );

        $templates =
            new EloquentCollection();

        foreach (
            SmsTemplateCatalog::editableTypes()
            as $type
        ) {
            $template =
                $stored->get(
                    $type->value
                );

            if (! $template instanceof SmsTemplate) {
                $template =
                    new SmsTemplate();

                $template->type =
                    $type->value;

                $template->template =
                    SmsTemplateCatalog::defaultTemplate(
                        $type
                    );
            }

            $templates->push(
                $template
            );
        }

        return $templates;
    }

    /**
     * Update an editable SMS template.
     *
     * Preserve transaction safety, template
     * restrictions, and administrator audit logging.
     */
    public function updateTemplate(
        User $admin,
        string $typeValue,
        string $templateText
    ): SmsTemplate {
        $type =
            SmsNotificationType::tryFrom(
                $typeValue
            );

        if (
            $type === null
            || ! SmsTemplateCatalog::isEditable(
                $type
            )
        ) {
            throw (
                new ModelNotFoundException()
            )->setModel(
                SmsTemplate::class,
                [$typeValue]
            );
        }

        $result =
            DB::transaction(
                function () use (
                    $admin,
                    $type,
                    $templateText
                ): array {
                    $smsTemplate =
                        SmsTemplate::query()
                            ->where(
                                'type',
                                $type->value
                            )
                            ->lockForUpdate()
                            ->first();

                    if (! $smsTemplate instanceof SmsTemplate) {
                        $smsTemplate =
                            new SmsTemplate();

                        $smsTemplate->type =
                            $type->value;

                        $smsTemplate->template =
                            SmsTemplateCatalog::defaultTemplate(
                                $type
                            );
                    }

                    $previousCharacterCount =
                        mb_strlen(
                            (string) $smsTemplate->template
                        );

                    $changed =
                        $smsTemplate->template
                        !== $templateText;

                    if ($changed) {
                        $smsTemplate->template =
                            $templateText;

                        $smsTemplate->updated_by =
                            $admin->id;

                        $smsTemplate->save();
                    }

                    return [
                        'template' =>
                            $smsTemplate,

                        'changed' =>
                            $changed,

                        'previous_character_count' =>
                            $previousCharacterCount,
                    ];
                }
            );

        /** @var SmsTemplate $updatedTemplate */
        $updatedTemplate =
            $result['template'];

        if ($result['changed']) {
            $this
                ->auditLogger
                ->recordSafely(
                    actor:
                        $admin,

                    event:
                        AuditEvent::SmsTemplateUpdated,

                    auditable:
                        $updatedTemplate,

                    metadata: [
                        'source' =>
                            'admin_system_settings',

                        'sms_type' =>
                            $type->value,

                        'previous_character_count' =>
                            $result[
                                'previous_character_count'
                            ],

                        'new_character_count' =>
                            mb_strlen(
                                $templateText
                            ),
                    ],
                );
        }

        return $updatedTemplate
            ->fresh()
            ?? $updatedTemplate;
    }
}
