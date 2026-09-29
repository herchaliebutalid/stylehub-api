<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\UpdateCustomerPersonalInformationRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\Customer\CustomerPersonalInformationService;
use Illuminate\Http\JsonResponse;

class CustomerProfileController extends Controller
{
    public function __construct(
        private readonly CustomerPersonalInformationService $profileService
    ) {}

    public function update(
        UpdateCustomerPersonalInformationRequest $request
    ): JsonResponse {
        $result =
            $this
                ->profileService
                ->update(
                    customer:
                        $request->user(),

                    data:
                        $request->validated(),
                );

        $phoneChanged =
            $result[
                'phone_changed'
            ];

        return response()->json([
            'success' =>
                true,

            'message' =>
                $phoneChanged
                    ? 'Personal information updated. Please verify your new mobile number.'
                    : 'Personal information updated successfully.',

            'data' => [
                'user' =>
                    (
                        new UserResource(
                            $result[
                                'user'
                            ]
                        )
                    )->resolve(
                        $request
                    ),

                'requires_phone_verification' =>
                    $phoneChanged,
            ],
        ]);
    }
}