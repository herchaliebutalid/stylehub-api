<?php

namespace App\Http\Controllers\Api\V1\ShopOwner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShopOwner\ApproveRescheduleRequest;
use App\Http\Requests\Api\V1\ShopOwner\RejectRescheduleRequest;
use App\Http\Resources\Api\V1\ShopOwnerRescheduleRequestResource;
use App\Models\RescheduleRequest;
use App\Models\Shop;
use App\Services\Appointments\ShopOwnerRescheduleWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RescheduleRequestController extends Controller
{
    public function __construct(
        private readonly ShopOwnerRescheduleWorkflowService $workflow
    ) {}

    /**
     * List reschedule requests for this shop only.
     */
    public function index(
        Request $request
    ): JsonResponse {
        $shop = $this->authenticatedShop(
            $request
        );

        $requests =
            RescheduleRequest::query()
                ->whereHas(
                    'appointment',
                    fn ($query) =>
                        $query->where(
                            'shop_id',
                            $shop->id
                        )
                )
                ->with([
                    'appointment.customer',
                    'reviewer',
                ])
                ->latest()
                ->get();

        return response()->json([
            'success' => true,

            'data' => [
                'reschedule_requests' =>
                    ShopOwnerRescheduleRequestResource::collection(
                        $requests
                    )->resolve($request),
            ],
        ]);
    }

    /**
     * Show one request belonging to this shop.
     */
    public function show(
        Request $request,
        string $rescheduleRequest
    ): JsonResponse {
        $shop = $this->authenticatedShop(
            $request
        );

        $ownedRequest =
            RescheduleRequest::query()
                ->whereKey(
                    $rescheduleRequest
                )
                ->whereHas(
                    'appointment',
                    fn ($query) =>
                        $query->where(
                            'shop_id',
                            $shop->id
                        )
                )
                ->with([
                    'appointment.customer',
                    'reviewer',
                ])
                ->firstOrFail();

        return response()->json([
            'success' => true,

            'data' => [
                'reschedule_request' => (
                    new ShopOwnerRescheduleRequestResource(
                        $ownedRequest
                    )
                )->resolve($request),
            ],
        ]);
    }

    /**
     * Approve and atomically apply a requested appointment slot.
     */
    public function approve(
        ApproveRescheduleRequest $request,
        string $rescheduleRequest
    ): JsonResponse {
        $shop = $this->authenticatedShop(
            $request
        );

        $updated =
            $this->workflow->approve(
                shop: $shop,
                owner: $request->user(),
                requestId:
                    (int) $rescheduleRequest,
            );

        return response()->json([
            'success' => true,

            'message' =>
                'Reschedule request approved successfully.',

            'data' => [
                'reschedule_request' => (
                    new ShopOwnerRescheduleRequestResource(
                        $updated
                    )
                )->resolve($request),
            ],
        ]);
    }

    /**
     * Reject a pending request without changing the appointment.
     */
    public function reject(
        RejectRescheduleRequest $request,
        string $rescheduleRequest
    ): JsonResponse {
        $shop = $this->authenticatedShop(
            $request
        );

        $updated =
            $this->workflow->reject(
                shop: $shop,
                owner: $request->user(),
                requestId:
                    (int) $rescheduleRequest,
                reason:
                    $request->validated(
                        'reason'
                    ),
            );

        return response()->json([
            'success' => true,

            'message' =>
                'Reschedule request rejected successfully.',

            'data' => [
                'reschedule_request' => (
                    new ShopOwnerRescheduleRequestResource(
                        $updated
                    )
                )->resolve($request),
            ],
        ]);
    }

    private function authenticatedShop(
        Request $request
    ): Shop {
        return $request->user()
            ->shop()
            ->firstOrFail();
    }
}