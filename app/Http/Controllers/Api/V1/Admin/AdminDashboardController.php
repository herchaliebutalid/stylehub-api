<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardService;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardService $dashboard
    ) {}

    /**
     * Return operational dashboard statistics.
     *
     * Administrator web application only.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' =>
                true,

            'data' =>
                $this->dashboard
                    ->summary(),
        ]);
    }
}