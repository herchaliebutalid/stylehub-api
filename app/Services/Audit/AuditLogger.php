<?php

namespace App\Services\Audit;

use App\Enums\AuditEvent;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditMetadataSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class AuditLogger
{
    /**
     * Record one immutable StyleHub audit event.
     *
     * This is the strict version. Exceptions are allowed to propagate.
     *
     * @param array<string|int, mixed> $metadata
     */
    public function record(
        ?User $actor,
        AuditEvent $event,
        ?Model $auditable = null,
        array $metadata = []
    ): AuditLog {
        $safeMetadata =
            AuditMetadataSanitizer::sanitize(
                $metadata
            );

        $request =
            $this->currentRequest();

        $log =
            new AuditLog();

        /*
        |--------------------------------------------------------------------------
        | Actor
        |--------------------------------------------------------------------------
        */

        $log->actor_id =
            $actor?->id;

        $log->actor_role =
            $this->actorRole(
                $actor
            );

        /*
        |--------------------------------------------------------------------------
        | Event
        |--------------------------------------------------------------------------
        */

        $log->event =
            $event;

        /*
        |--------------------------------------------------------------------------
        | Auditable Resource
        |--------------------------------------------------------------------------
        */

        if ($auditable !== null) {
            $log->auditable_type =
                $auditable->getMorphClass();

            $log->auditable_id =
                $auditable->getKey();
        }

        /*
        |--------------------------------------------------------------------------
        | Request Context
        |--------------------------------------------------------------------------
        */

        $log->ip_address =
            $request?->ip();

        $userAgent =
            $request?->userAgent();

        $log->user_agent =
            is_string($userAgent)
                ? mb_substr(
                    $userAgent,
                    0,
                    500
                )
                : null;

        /*
        |--------------------------------------------------------------------------
        | Safe Metadata
        |--------------------------------------------------------------------------
        */

        $log->metadata =
            $safeMetadata === []
                ? null
                : $safeMetadata;

        $log->created_at =
            now();

        $log->save();

        return $log;
    }

    /**
     * Safely record an audit event.
     *
     * A failure in audit storage must not make an already committed
     * business operation appear to have failed to the user.
     *
     * @param array<string|int, mixed> $metadata
     */
    public function recordSafely(
        ?User $actor,
        AuditEvent $event,
        ?Model $auditable = null,
        array $metadata = []
    ): ?AuditLog {
        try {
            return $this->record(
                actor:
                    $actor,

                event:
                    $event,

                auditable:
                    $auditable,

                metadata:
                    $metadata,
            );
        } catch (Throwable $exception) {
            report(
                $exception
            );

            return null;
        }
    }

    /**
     * Record an event using the currently authenticated account.
     *
     * If there is no authenticated user, nothing is recorded.
     *
     * This prevents factories, seeders, and ordinary CLI operations
     * from creating misleading user audit entries.
     *
     * @param array<string|int, mixed> $metadata
     */
    public function recordCurrentSafely(
        AuditEvent $event,
        ?Model $auditable = null,
        array $metadata = []
    ): ?AuditLog {
        $actor =
            $this->currentActor();

        if ($actor === null) {
            return null;
        }

        return $this->recordSafely(
            actor:
                $actor,

            event:
                $event,

            auditable:
                $auditable,

            metadata:
                $metadata,
        );
    }

    /**
     * Resolve the currently authenticated StyleHub user.
     */
    public function currentActor(): ?User
    {
        $request =
            $this->currentRequest();

        $requestUser =
            $request?->user();

        if ($requestUser instanceof User) {
            return $requestUser;
        }

        /*
        |--------------------------------------------------------------------------
        | Sanctum Fallback
        |--------------------------------------------------------------------------
        */

        try {
            $sanctumUser =
                Auth::guard(
                    'sanctum'
                )->user();

            if ($sanctumUser instanceof User) {
                return $sanctumUser;
            }
        } catch (Throwable) {
            // Continue to the default guard fallback.
        }

        /*
        |--------------------------------------------------------------------------
        | Default Guard Fallback
        |--------------------------------------------------------------------------
        */

        try {
            $defaultUser =
                Auth::user();

            if ($defaultUser instanceof User) {
                return $defaultUser;
            }
        } catch (Throwable) {
            // No authenticated application user.
        }

        return null;
    }

    private function actorRole(
        ?User $actor
    ): ?string {
        if ($actor === null) {
            return null;
        }

        $role =
            $actor->role;

        if ($role instanceof UserRole) {
            return $role->value;
        }

        if (is_string($role)) {
            return $role;
        }

        return null;
    }

    private function currentRequest(): ?Request
    {
        if (
            ! app()->bound(
                'request'
            )
        ) {
            return null;
        }

        $request =
            app(
                'request'
            );

        return $request instanceof Request
            ? $request
            : null;
    }
}