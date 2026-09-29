<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RescheduleRequestResource;
use App\Models\RescheduleRequest;
use App\Services\Appointments\CustomerAppointmentWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RescheduleRequestController extends Controller
{
    public function __construct(
        private readonly CustomerAppointmentWorkflowService $workflow
    ) {}

    /**
     * List only this customer's reschedule requests.
     */
    public function index(
        Request $request
    ): JsonResponse {
        $requests =
            RescheduleRequest::query()
                ->whereHas(
                    'appointment',
                    fn ($query) =>
                        $query->where(
                            'customer_id',
                            $request->user()->id
                        )
                )
                ->with([
                    'appointment.shop',
                ])
                ->latest()
                ->get();

        return response()->json([
            'success' => true,

            'data' => [
                'reschedule_requests' =>
                    RescheduleRequestResource::collection(
                        $requests
                    )->resolve($request),
            ],
        ]);
    }

    /**
     * Show only this customer's request.
     */
    public function show(
        Request $request,
        string $rescheduleRequest
    ): JsonResponse {
        $ownedRequest =
            RescheduleRequest::query()
                ->whereKey(
                    $rescheduleRequest
                )
                ->whereHas(
                    'appointment',
                    fn ($query) =>
                        $query->where(
                            'customer_id',
                            $request->user()->id
                        )
                )
                ->with([
                    'appointment.shop',
                ])
                ->firstOrFail();

        return response()->json([
            'success' => true,

            'data' => [
                'reschedule_request' => (
                    new RescheduleRequestResource(
                        $ownedRequest
                    )
                )->resolve($request),
            ],
        ]);
    }

    /**
     * Withdraw one pending request.
     */
    public function withdraw(
        Request $request,
        string $rescheduleRequest
    ): JsonResponse {
        $updated =
            $this->workflow->withdraw(
                customer:
                    $request->user(),

                requestId:
                    (int) $rescheduleRequest,
            );

        return response()->json([
            'success' => true,

            'message' =>
                'Reschedule request withdrawn successfully.',

            'data' => [
                'reschedule_request' => (
                    new RescheduleRequestResource(
                        $updated
                    )
                )->resolve($request),
            ],
        ]);
    }
}