<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateAdminAccountProfileRequest;
use App\Http\Requests\Api\V1\Auth\ChangePasswordRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\Admin\AdminAccountSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAccountController extends Controller
{
    public function __construct(
        private readonly AdminAccountSettingsService $accountSettings
    ) {}

    public function show(
        Request $request
    ): JsonResponse {
        return response()->json([
            'success' =>
                true,

            'data' => [
                'user' =>
                    (
                        new UserResource(
                            $request->user()
                        )
                    )->resolve(
                        $request
                    ),
            ],
        ]);
    }

    public function updateProfile(
        UpdateAdminAccountProfileRequest $request
    ): JsonResponse {
        $admin =
            $this
                ->accountSettings
                ->updateProfile(
                    admin:
                        $request->user(),

                    attributes:
                        $request->validated(),
                );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Administrator profile updated successfully.',

            'data' => [
                'user' =>
                    (
                        new UserResource(
                            $admin
                        )
                    )->resolve(
                        $request
                    ),
            ],
        ]);
    }

    public function changePassword(
        ChangePasswordRequest $request
    ): JsonResponse {
        $validated =
            $request->validated();

        $this
            ->accountSettings
            ->changePassword(
                admin:
                    $request->user(),

                currentPassword:
                    $validated[
                        'current_password'
                    ],

                newPassword:
                    $validated[
                        'password'
                    ],
            );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Password changed successfully. Other signed-in sessions were revoked.',
        ]);
    }
}