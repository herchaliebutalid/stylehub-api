<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AdminReportOverviewRequest;
use App\Services\Admin\AdminReportService;
use Illuminate\Http\JsonResponse;

class AdminReportController extends Controller
{
    public function __construct(
        private readonly AdminReportService $reports
    ) {}

    /**
     * Return safe aggregated Administrator reporting information.
     *
     * This endpoint is intentionally read-only.
     */
    public function overview(
        AdminReportOverviewRequest $request
    ): JsonResponse {
        return response()->json([
            'success' =>
                true,

            'data' =>
                $this
                    ->reports
                    ->overview(
                        $request->validated()
                    ),

            'generated_at' =>
                now()
                    ->toISOString(),
        ]);
    }
}