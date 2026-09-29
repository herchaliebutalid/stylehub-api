<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\StoreFeedbackRequest;
use App\Http\Resources\Api\V1\CustomerFeedbackResource;
use App\Models\Feedback;
use App\Services\Feedback\CustomerFeedbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FeedbackController extends Controller
{
    public function __construct(
        private readonly CustomerFeedbackService $feedbackService
    ) {}

    /**
     * List only feedback submitted by the authenticated customer.
     */
    public function index(
        Request $request
    ): JsonResponse {
        $feedback =
            Feedback::query()
                ->where(
                    'customer_id',
                    $request->user()->id
                )
                ->with([
                    'appointment',
                    'shop',
                ])
                ->orderByDesc(
                    'created_at'
                )
                ->get();

        return response()->json([
            'success' => true,

            'data' => [
                'feedback' =>
                    CustomerFeedbackResource::collection(
                        $feedback
                    )->resolve($request),
            ],
        ]);
    }

    /**
     * Submit feedback for one owned completed appointment.
     */
    public function store(
        StoreFeedbackRequest $request,
        string $appointment
    ): JsonResponse {
        $feedback =
            $this->feedbackService->submit(
                customer:
                    $request->user(),

                appointmentId:
                    (int) $appointment,

                data:
                    $request->validated(),
            );

        return response()->json([
            'success' => true,

            'message' =>
                'Feedback submitted successfully.',

            'data' => [
                'feedback' => (
                    new CustomerFeedbackResource(
                        $feedback
                    )
                )->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Show one review belonging only to this customer.
     */
    public function show(
        Request $request,
        string $feedback
    ): JsonResponse {
        $ownedFeedback =
            Feedback::query()
                ->where(
                    'customer_id',
                    $request->user()->id
                )
                ->whereKey(
                    $feedback
                )
                ->with([
                    'appointment',
                    'shop',
                ])
                ->firstOrFail();

        return response()->json([
            'success' => true,

            'data' => [
                'feedback' => (
                    new CustomerFeedbackResource(
                        $ownedFeedback
                    )
                )->resolve($request),
            ],
        ]);
    }
}