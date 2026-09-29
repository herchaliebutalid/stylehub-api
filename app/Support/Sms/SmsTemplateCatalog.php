<?php

namespace App\Support\Sms;

use App\Enums\SmsNotificationType;
use InvalidArgumentException;

class SmsTemplateCatalog
{
    public const MAX_TEMPLATE_CHARACTERS = 320;

    /**
     * @var array<string, array{
     *     description: string,
     *     template: string,
     *     placeholders: list<string>
     * }>
     */
    private const DEFINITIONS = [
        'booking_received' => [
            'description' =>
                'Sent to the customer when a new booking is received.',

            'template' =>
                'StyleHub: We received your booking at {shop_name} for {service_name} on {appointment_date} at {appointment_time}. Status: Pending.',

            'placeholders' => [
                'shop_name',
                'service_name',
                'appointment_date',
                'appointment_time',
            ],
        ],

        'appointment_confirmed' => [
            'description' =>
                'Sent when the shop confirms an appointment.',

            'template' =>
                'StyleHub: Your appointment at {shop_name} on {appointment_date} at {appointment_time} is confirmed.',

            'placeholders' => [
                'shop_name',
                'appointment_date',
                'appointment_time',
            ],
        ],

        'appointment_declined' => [
            'description' =>
                'Sent when the shop declines an appointment request.',

            'template' =>
                'StyleHub: Your appointment request at {shop_name} on {appointment_date} at {appointment_time} was declined. Open StyleHub for details.',

            'placeholders' => [
                'shop_name',
                'appointment_date',
                'appointment_time',
            ],
        ],

        'appointment_cancelled' => [
            'description' =>
                'Sent when an appointment is cancelled.',

            'template' =>
                'StyleHub: Your appointment at {shop_name} on {appointment_date} at {appointment_time} was cancelled. Open StyleHub for details.',

            'placeholders' => [
                'shop_name',
                'appointment_date',
                'appointment_time',
            ],
        ],

        'reschedule_approved' => [
            'description' =>
                'Sent when a reschedule request is approved.',

            'template' =>
                'StyleHub: Your reschedule request was approved. New schedule: {appointment_date} at {appointment_time} at {shop_name}.',

            'placeholders' => [
                'appointment_date',
                'appointment_time',
                'shop_name',
            ],
        ],

        'reschedule_rejected' => [
            'description' =>
                'Sent when a reschedule request is rejected.',

            'template' =>
                'StyleHub: Your reschedule request for {requested_date} at {requested_time} at {shop_name} was not approved. Your current appointment remains unchanged.',

            'placeholders' => [
                'requested_date',
                'requested_time',
                'shop_name',
            ],
        ],

        'appointment_reminder' => [
            'description' =>
                'Sent before a confirmed appointment according to the configured reminder window.',

            'template' =>
                'StyleHub reminder: Your confirmed appointment at {shop_name} is on {appointment_date} at {appointment_time}.',

            'placeholders' => [
                'shop_name',
                'appointment_date',
                'appointment_time',
            ],
        ],
    ];

    /**
     * The controlled provider verification message is intentionally excluded.
     *
     * @return list<SmsNotificationType>
     */
    public static function editableTypes(): array
    {
        return array_map(
            static fn (
                string $value
            ): SmsNotificationType =>
                SmsNotificationType::from(
                    $value
                ),
            array_keys(
                self::DEFINITIONS
            )
        );
    }

    /**
     * @return list<string>
     */
    public static function editableValues(): array
    {
        return array_keys(
            self::DEFINITIONS
        );
    }

    public static function isEditable(
        SmsNotificationType $type
    ): bool {
        return isset(
            self::DEFINITIONS[
                $type->value
            ]
        );
    }

    public static function description(
        SmsNotificationType $type
    ): string {
        return self::definition(
            $type
        )['description'];
    }

    public static function defaultTemplate(
        SmsNotificationType $type
    ): string {
        return self::definition(
            $type
        )['template'];
    }

    /**
     * @return list<string>
     */
    public static function allowedPlaceholderNames(
        SmsNotificationType $type
    ): array {
        return self::definition(
            $type
        )['placeholders'];
    }

    /**
     * @return list<string>
     */
    public static function allowedPlaceholders(
        SmsNotificationType $type
    ): array {
        return array_map(
            static fn (
                string $name
            ): string =>
                '{'.$name.'}',
            self::allowedPlaceholderNames(
                $type
            )
        );
    }

    public static function validationError(
        SmsNotificationType $type,
        string $template
    ): ?string {
        $withoutTokens =
            preg_replace(
                '/\{[a-z_][a-z0-9_]*\}/u',
                '',
                $template
            );

        if (
            ! is_string($withoutTokens)
            || str_contains(
                $withoutTokens,
                '{'
            )
            || str_contains(
                $withoutTokens,
                '}'
            )
        ) {
            return 'Placeholders must use the exact {placeholder_name} format.';
        }

        preg_match_all(
            '/\{([a-z_][a-z0-9_]*)\}/u',
            $template,
            $matches
        );

        $usedNames =
            array_values(
                array_unique(
                    $matches[1]
                    ?? []
                )
            );

        $unknown =
            array_values(
                array_diff(
                    $usedNames,
                    self::allowedPlaceholderNames(
                        $type
                    )
                )
            );

        if ($unknown !== []) {
            return 'Unsupported placeholder(s): '
                .implode(
                    ', ',
                    array_map(
                        static fn (
                            string $name
                        ): string =>
                            '{'.$name.'}',
                        $unknown
                    )
                )
                .'.';
        }

        return null;
    }

    /**
     * @return array{
     *     description: string,
     *     template: string,
     *     placeholders: list<string>
     * }
     */
    private static function definition(
        SmsNotificationType $type
    ): array {
        $definition =
            self::DEFINITIONS[
                $type->value
            ]
            ?? null;

        if ($definition === null) {
            throw new InvalidArgumentException(
                'This SMS notification type does not have an editable template.'
            );
        }

        return $definition;
    }
}