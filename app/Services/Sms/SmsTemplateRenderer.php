<?php

namespace App\Services\Sms;

use App\Enums\SmsNotificationType;
use App\Models\SmsTemplate;
use App\Support\Sms\SmsTemplateCatalog;

class SmsTemplateRenderer
{
    /**
     * Render one customer-facing SMS message.
     *
     * Missing database rows safely fall back to the application default so
     * notification delivery is not coupled to an accidental missing row.
     *
     * @param array<string, mixed> $values
     */
    public function render(
        SmsNotificationType $type,
        array $values
    ): string {
        $template =
            SmsTemplate::query()
                ->where(
                    'type',
                    $type->value
                )
                ->value(
                    'template'
                );

        if (
            ! is_string($template)
            || trim($template) === ''
        ) {
            $template =
                SmsTemplateCatalog::defaultTemplate(
                    $type
                );
        }

        $replacements = [];

        foreach (
            SmsTemplateCatalog::allowedPlaceholderNames(
                $type
            ) as $name
        ) {
            $value =
                $values[$name]
                ?? '';

            $replacements[
                '{'.$name.'}'
            ] = is_scalar($value)
                ? (string) $value
                : '';
        }

        return trim(
            strtr(
                $template,
                $replacements
            )
        );
    }
}