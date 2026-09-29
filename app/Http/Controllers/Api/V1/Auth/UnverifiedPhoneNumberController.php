<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\UpdateUnverifiedPhoneNumberRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\Auth\UnverifiedPhoneNumberService;
use Illuminate\Http\JsonResponse;

class UnverifiedPhoneNumberController extends Controller
{
    public function __construct(
        private readonly UnverifiedPhoneNumberService $phoneNumberService
    ) {}

    public function __invoke(
        UpdateUnverifiedPhoneNumberRequest $request
    ): JsonResponse {
        $validated =
            $request->validated();

        $user =
            $this
                ->phoneNumberService
                ->update(
                    user:
                        $request->user(),

                    phoneNumber:
                        $validated['phone_number'],

                    currentPassword:
                        $validated['current_password'],
                );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Phone number updated. Please verify the new number before using protected features.',

            'data' => [
                'user' =>
                    (
                        new UserResource(
                            $user
                        )
                    )->resolve(
                        $request
                    ),
            ],
        ]);
    }
}