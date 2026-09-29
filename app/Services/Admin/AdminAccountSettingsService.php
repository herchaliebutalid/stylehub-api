<?php

namespace App\Services\Admin;

use App\Enums\AuditEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\PasswordRecoveryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class AdminAccountSettingsService
{
    public function __construct(
        private readonly PasswordRecoveryService $passwordRecoveryService,
        private readonly AuditLogger $auditLogger
    ) {}

    /**
     * @param array{
     *     first_name: string,
     *     middle_name: ?string,
     *     last_name: string,
     *     email: string
     * } $attributes
     */
    public function updateProfile(
        User $admin,
        array $attributes
    ): User {
        $result =
            DB::transaction(
                function () use (
                    $admin,
                    $attributes
                ): array {
                    $lockedAdmin =
                        User::query()
                            ->whereKey(
                                $admin->id
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    if (
                        ! $lockedAdmin->isAdmin()
                        || ! $lockedAdmin->isActive()
                    ) {
                        throw new AuthorizationException(
                            'Administrator account access is not permitted.'
                        );
                    }

                    $changedFields = [];

                    foreach (
                        [
                            'first_name',
                            'middle_name',
                            'last_name',
                            'email',
                        ] as $field
                    ) {
                        if (
                            $lockedAdmin->{$field}
                            === $attributes[$field]
                        ) {
                            continue;
                        }

                        $changedFields[] =
                            $field;

                        $lockedAdmin->{$field} =
                            $attributes[$field];
                    }

                    $emailChanged =
                        in_array(
                            'email',
                            $changedFields,
                            true
                        );

                    if ($emailChanged) {
                        $lockedAdmin->email_verified_at =
                            null;
                    }

                    if ($changedFields !== []) {
                        $lockedAdmin->save();
                    }

                    return [
                        'admin' =>
                            $lockedAdmin,

                        'changed_fields' =>
                            $changedFields,

                        'email_verification_reset' =>
                            $emailChanged,
                    ];
                }
            );

        /** @var User $updatedAdmin */
        $updatedAdmin =
            $result['admin'];

        if (
            $result['changed_fields']
            !== []
        ) {
            $this
                ->auditLogger
                ->recordSafely(
                    actor:
                        $updatedAdmin,

                    event:
                        AuditEvent::AdminAccountUpdated,

                    auditable:
                        $updatedAdmin,

                    metadata: [
                        'source' =>
                            'admin_account_settings',

                        'changed_fields' =>
                            $result[
                                'changed_fields'
                            ],

                        'email_verification_reset' =>
                            $result[
                                'email_verification_reset'
                            ],
                    ],
                );
        }

        return $updatedAdmin
            ->fresh();
    }

    public function changePassword(
        User $admin,
        string $currentPassword,
        string $newPassword
    ): void {
        if (
            ! $admin->isAdmin()
            || ! $admin->isActive()
        ) {
            throw new AuthorizationException(
                'Administrator account access is not permitted.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Reuse Existing Hardened Password Change
        |--------------------------------------------------------------------------
        |
        | This already:
        |
        | - verifies the current password;
        | - rejects reuse of the current password;
        | - applies the application's strong password policy;
        | - keeps the current Sanctum token;
        | - revokes every other Sanctum token;
        | - invalidates unused password-reset codes.
        |
        */

        $this
            ->passwordRecoveryService
            ->changePassword(
                user:
                    $admin,

                currentPassword:
                    $currentPassword,

                newPassword:
                    $newPassword,
            );

        $this
            ->auditLogger
            ->recordSafely(
                actor:
                    $admin,

                event:
                    AuditEvent::AdminPasswordChanged,

                auditable:
                    $admin,

                metadata: [
                    'source' =>
                        'admin_account_settings',

                    'other_sessions_revoked' =>
                        true,
                ],
            );
    }
}