<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AuditResourceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\BrowseAuditLogsRequest;
use App\Http\Resources\Api\V1\AdminAuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Browse immutable system audit history.
     *
     * Administrator only.
     */
    public function index(
        BrowseAuditLogsRequest $request
    ): JsonResponse {
        $validated =
            $request->validated();

        $query =
            AuditLog::query();

        /*
        |--------------------------------------------------------------------------
        | Event
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $validated['event']
            )
        ) {
            $query->where(
                'event',
                $validated['event']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Actor
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $validated['actor_id']
            )
        ) {
            $query->where(
                'actor_id',
                $validated['actor_id']
            );
        }

        if (
            isset(
                $validated['actor_role']
            )
        ) {
            $query->where(
                'actor_role',
                $validated['actor_role']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Resource Type
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $validated['resource_type']
            )
        ) {
            $resourceType =
                AuditResourceType::from(
                    $validated[
                        'resource_type'
                    ]
                );

            $query->where(
                'auditable_type',
                $resourceType
                    ->morphClass()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Date Range
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $validated['date_from']
            )
        ) {
            $query->whereDate(
                'created_at',
                '>=',
                $validated['date_from']
            );
        }

        if (
            isset(
                $validated['date_to']
            )
        ) {
            $query->whereDate(
                'created_at',
                '<=',
                $validated['date_to']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        $perPage =
            (int) (
                $validated['per_page']
                ?? 20
            );

        $logs =
            $query
                ->orderByDesc(
                    'created_at'
                )
                ->orderByDesc(
                    'id'
                )
                ->paginate(
                    $perPage
                );

        return response()->json([
            'success' =>
                true,

            'data' => [
                'audit_logs' =>
                    AdminAuditLogResource::collection(
                        collect(
                            $logs->items()
                        )
                    )->resolve($request),

                'pagination' => [
                    'current_page' =>
                        $logs->currentPage(),

                    'per_page' =>
                        $logs->perPage(),

                    'total' =>
                        $logs->total(),

                    'last_page' =>
                        $logs->lastPage(),

                    'has_more_pages' =>
                        $logs->hasMorePages(),
                ],
            ],
        ]);
    }

    /**
     * Show one immutable audit entry.
     *
     * Administrator only.
     */
    public function show(
        Request $request,
        string $auditLog
    ): JsonResponse {
        $log =
            AuditLog::query()
                ->whereKey(
                    $auditLog
                )
                ->firstOrFail();

        return response()->json([
            'success' =>
                true,

            'data' => [
                'audit_log' => (
                    new AdminAuditLogResource(
                        $log
                    )
                )->resolve($request),
            ],
        ]);
    }
}