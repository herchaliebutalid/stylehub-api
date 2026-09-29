<?php

namespace App\Enums;

use App\Models\Appointment;
use App\Models\Feedback;
use App\Models\Promo;
use App\Models\RescheduleRequest;
use App\Models\Service;
use App\Models\Shop;
use App\Models\ShopSchedule;
use App\Models\SmsNotification;
use App\Models\SmsTemplate;
use App\Models\User;

enum AuditResourceType: string
{
    case User =
        'user';

    case Shop =
        'shop';

    case Service =
        'service';

    case ShopSchedule =
        'shop_schedule';

    case Appointment =
        'appointment';

    case RescheduleRequest =
        'reschedule_request';

    case Promo =
        'promo';

    case Feedback =
        'feedback';

    case SmsNotification =
        'sms_notification';

    case SmsTemplate =
        'sms_template';

    /**
     * @return class-string
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::User =>
                User::class,

            self::Shop =>
                Shop::class,

            self::Service =>
                Service::class,

            self::ShopSchedule =>
                ShopSchedule::class,

            self::Appointment =>
                Appointment::class,

            self::RescheduleRequest =>
                RescheduleRequest::class,

            self::Promo =>
                Promo::class,

            self::Feedback =>
                Feedback::class,

            self::SmsNotification =>
                SmsNotification::class,

            self::SmsTemplate =>
                SmsTemplate::class,
        };
    }

    public function morphClass(): string
    {
        $class =
            $this->modelClass();

        return (
            new $class()
        )->getMorphClass();
    }

    public static function fromStoredMorph(
        ?string $storedMorph
    ): ?self {
        if (
            $storedMorph === null
            || $storedMorph === ''
        ) {
            return null;
        }

        foreach (
            self::cases()
            as $case
        ) {
            if (
                $case->morphClass()
                === $storedMorph
            ) {
                return $case;
            }
        }

        return null;
    }

    public function label(): string
    {
        return match ($this) {
            self::User =>
                'User',

            self::Shop =>
                'Shop',

            self::Service =>
                'Service',

            self::ShopSchedule =>
                'Shop Schedule',

            self::Appointment =>
                'Appointment',

            self::RescheduleRequest =>
                'Reschedule Request',

            self::Promo =>
                'Promotion',

            self::Feedback =>
                'Feedback',

            self::SmsNotification =>
                'SMS Notification',

            self::SmsTemplate =>
                'SMS Template',
        };
    }
}