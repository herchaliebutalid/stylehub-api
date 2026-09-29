<?php

namespace App\Services\Admin;

use App\Enums\AuditEvent;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AdminUserManagementService
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    /**
     * Suspend a Customer or Shop Owner account.
     *
     * Existing Sanctum tokens are revoked immediately.
     */
    public function suspend(
        int $userId
    ): User {
        return DB::transaction(
            function () use (
                $userId
            ): User {
                $user =
                    $this
                        ->managedUserQuery()
                        ->whereKey(
                            $userId
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    $user === null
                ) {
                    throw (
                        new ModelNotFoundException()
                    )->setModel(
                        User::class,
                        [
                            $userId,
                        ]
                    );
                }

                if (
                    $user->status
                    === UserStatus::Suspended
                ) {
                    throw new ConflictHttpException(
                        'This account is already suspended.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Remember Previous Status
                |--------------------------------------------------------------------------
                */

                $previousStatus =
                    $user->status->value;

                /*
                |--------------------------------------------------------------------------
                | Count Existing Sessions
                |--------------------------------------------------------------------------
                |
                | We count the existing Sanctum sessions before revoking them.
                |
                | Only the number is recorded in the audit log.
                |
                | Actual token values and token identifiers are never written
                | into audit metadata.
                |
                */

                $revokedSessionCount =
                    $user
                        ->tokens()
                        ->count();

                /*
                |--------------------------------------------------------------------------
                | Suspend Account
                |--------------------------------------------------------------------------
                |
                | status is intentionally controlled by trusted server-side
                | code rather than mass assignment.
                |
                */

                $user->forceFill([
                    'status' =>
                        UserStatus::Suspended,
                ])->save();

                /*
                |--------------------------------------------------------------------------
                | Revoke Existing Sanctum Sessions
                |--------------------------------------------------------------------------
                |
                | This immediately invalidates every existing mobile session
                | belonging to the suspended Customer or Shop Owner.
                |
                */

                $user
                    ->tokens()
                    ->delete();

                /*
                |--------------------------------------------------------------------------
                | Security Audit
                |--------------------------------------------------------------------------
                |
                | IMPORTANT:
                |
                | AuditMetadataSanitizer intentionally rejects metadata field
                | names containing sensitive words such as "token".
                |
                | Therefore we use:
                |
                | revoked_session_count
                |
                | instead of:
                |
                | revoked_token_count
                |
                | No credential, token value or token identifier is recorded.
                |
                */

                $this
                    ->auditLogger
                    ->recordCurrentSafely(
                        AuditEvent::UserSuspended,
                        $user,
                        [
                            'previous_status' =>
                                $previousStatus,

                            'new_status' =>
                                UserStatus::Suspended
                                    ->value,

                            'revoked_session_count' =>
                                $revokedSessionCount,
                        ]
                    );

                /*
                |--------------------------------------------------------------------------
                | Reload Safe Related Data
                |--------------------------------------------------------------------------
                */

                $user->load([
                    'shop',
                ]);

                return $user;
            },
            3
        );
    }

    /**
     * Reactivate a suspended Customer or Shop Owner.
     *
     * Previously revoked Sanctum sessions are never restored.
     *
     * The user must log in again to receive a new authentication token.
     */
    public function reactivate(
        int $userId
    ): User {
        return DB::transaction(
            function () use (
                $userId
            ): User {
                $user =
                    $this
                        ->managedUserQuery()
                        ->whereKey(
                            $userId
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    $user === null
                ) {
                    throw (
                        new ModelNotFoundException()
                    )->setModel(
                        User::class,
                        [
                            $userId,
                        ]
                    );
                }

                if (
                    $user->status
                    === UserStatus::Active
                ) {
                    throw new ConflictHttpException(
                        'This account is already active.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Remember Previous Status
                |--------------------------------------------------------------------------
                */

                $previousStatus =
                    $user->status->value;

                /*
                |--------------------------------------------------------------------------
                | Reactivate Account
                |--------------------------------------------------------------------------
                */

                $user->forceFill([
                    'status' =>
                        UserStatus::Active,
                ])->save();

                /*
                |--------------------------------------------------------------------------
                | Security Audit
                |--------------------------------------------------------------------------
                */

                $this
                    ->auditLogger
                    ->recordCurrentSafely(
                        AuditEvent::UserReactivated,
                        $user,
                        [
                            'previous_status' =>
                                $previousStatus,

                            'new_status' =>
                                UserStatus::Active
                                    ->value,
                        ]
                    );

                /*
                |--------------------------------------------------------------------------
                | Reload Safe Related Data
                |--------------------------------------------------------------------------
                */

                $user->load([
                    'shop',
                ]);

                return $user;
            },
            3
        );
    }

    /**
     * Return accounts that the Administrator User Management API
     * is permitted to manage.
     *
     * Administrator accounts are intentionally excluded.
     *
     * @return Builder<User>
     */
    private function managedUserQuery(): Builder
    {
        return User::query()
            ->whereIn(
                'role',
                [
                    UserRole::Customer
                        ->value,

                    UserRole::ShopOwner
                        ->value,
                ]
            );
    }
}