<?php

namespace App\Services\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\ShopApprovalStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Feedback;
use App\Models\Promo;
use App\Models\Shop;
use App\Models\User;

class AdminDashboardService
{
    /**
     * Build the administrator dashboard summary.
     *
     * Only aggregate counts are returned.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'users' =>
                $this->userSummary(),

            'shops' =>
                $this->shopSummary(),

            'appointments' =>
                $this->appointmentSummary(),

            'other' =>
                $this->otherSummary(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userSummary(): array
    {
        return [
            'customers' => [
                'total' =>
                    User::query()
                        ->where(
                            'role',
                            UserRole::Customer->value
                        )
                        ->count(),

                'active' =>
                    User::query()
                        ->where(
                            'role',
                            UserRole::Customer->value
                        )
                        ->where(
                            'status',
                            UserStatus::Active->value
                        )
                        ->count(),

                'suspended' =>
                    User::query()
                        ->where(
                            'role',
                            UserRole::Customer->value
                        )
                        ->where(
                            'status',
                            UserStatus::Suspended->value
                        )
                        ->count(),
            ],

            'shop_owners' => [
                'total' =>
                    User::query()
                        ->where(
                            'role',
                            UserRole::ShopOwner->value
                        )
                        ->count(),

                'active' =>
                    User::query()
                        ->where(
                            'role',
                            UserRole::ShopOwner->value
                        )
                        ->where(
                            'status',
                            UserStatus::Active->value
                        )
                        ->count(),

                'suspended' =>
                    User::query()
                        ->where(
                            'role',
                            UserRole::ShopOwner->value
                        )
                        ->where(
                            'status',
                            UserStatus::Suspended->value
                        )
                        ->count(),
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function shopSummary(): array
    {
        return [
            'total' =>
                Shop::query()
                    ->count(),

            'pending' =>
                Shop::query()
                    ->where(
                        'approval_status',
                        ShopApprovalStatus::Pending->value
                    )
                    ->count(),

            'approved' =>
                Shop::query()
                    ->where(
                        'approval_status',
                        ShopApprovalStatus::Approved->value
                    )
                    ->count(),

            'rejected' =>
                Shop::query()
                    ->where(
                        'approval_status',
                        ShopApprovalStatus::Rejected->value
                    )
                    ->count(),
        ];
    }

    /**
     * Count every valid appointment status.
     *
     * No-show is a separate terminal status and must
     * not be omitted from dashboard reporting.
     *
     * @return array<string, int>
     */
    private function appointmentSummary(): array
    {
        return [
            'total' =>
                Appointment::query()
                    ->count(),

            'pending' =>
                Appointment::query()
                    ->where(
                        'status',
                        AppointmentStatus::Pending->value
                    )
                    ->count(),

            'confirmed' =>
                Appointment::query()
                    ->where(
                        'status',
                        AppointmentStatus::Confirmed->value
                    )
                    ->count(),

            'completed' =>
                Appointment::query()
                    ->where(
                        'status',
                        AppointmentStatus::Completed->value
                    )
                    ->count(),

            'no_show' =>
                Appointment::query()
                    ->where(
                        'status',
                        AppointmentStatus::NoShow->value
                    )
                    ->count(),

            'cancelled' =>
                Appointment::query()
                    ->where(
                        'status',
                        AppointmentStatus::Cancelled->value
                    )
                    ->count(),

            'declined' =>
                Appointment::query()
                    ->where(
                        'status',
                        AppointmentStatus::Declined->value
                    )
                    ->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function otherSummary(): array
    {
        return [
            'feedback' =>
                Feedback::query()
                    ->count(),

            'active_promos' =>
                Promo::query()
                    ->where(
                        'is_active',
                        true
                    )
                    ->count(),

            'audit_logs' =>
                AuditLog::query()
                    ->count(),
        ];
    }
}
