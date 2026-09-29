<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\BrowseAdminUsersRequest;
use App\Http\Resources\Api\V1\AdminUserResource;
use App\Models\User;
use App\Services\Admin\AdminUserManagementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly AdminUserManagementService $userManagement
    ) {}

    /**
     * Browse Customer and Shop Owner accounts.
     *
     * Administrator web only.
     */
    public function index(
        BrowseAdminUsersRequest $request
    ): JsonResponse {
        $validated =
            $request->validated();

        $query =
            $this
                ->managedUsers()
                ->with([
                    'shop',
                ])
                ->withCount([
                    'appointments',
                ]);

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $validated['search']
            )
        ) {
            $search =
                $validated['search'];

            $query->where(
                function (
                    Builder $query
                ) use (
                    $search
                ): void {
                    $query
                        ->where(
                            'first_name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'middle_name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'last_name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'email',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'phone_number',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Role
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $validated['role']
            )
        ) {
            $query->where(
                'role',
                $validated['role']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Account Status
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $validated['status']
            )
        ) {
            $query->where(
                'status',
                $validated['status']
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

        $users =
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
                'users' =>
                    AdminUserResource::collection(
                        collect(
                            $users->items()
                        )
                    )->resolve(
                        $request
                    ),

                'pagination' => [
                    'current_page' =>
                        $users->currentPage(),

                    'per_page' =>
                        $users->perPage(),

                    'total' =>
                        $users->total(),

                    'last_page' =>
                        $users->lastPage(),

                    'has_more_pages' =>
                        $users->hasMorePages(),
                ],
            ],
        ]);
    }

    /**
     * View one Customer or Shop Owner account.
     */
    public function show(
        Request $request,
        string $user
    ): JsonResponse {
        $userModel =
            $this
                ->managedUsers()
                ->with([
                    'shop',
                ])
                ->withCount([
                    'appointments',
                ])
                ->whereKey(
                    $user
                )
                ->firstOrFail();

        return response()->json([
            'success' =>
                true,

            'data' => [
                'user' =>
                    (
                        new AdminUserResource(
                            $userModel
                        )
                    )->resolve(
                        $request
                    ),
            ],
        ]);
    }

    /**
     * Suspend a Customer or Shop Owner.
     */
    public function suspend(
        Request $request,
        string $user
    ): JsonResponse {
        $managedUser =
            $this
                ->userManagement
                ->suspend(
                    (int) $user
                );

        $managedUser
            ->loadCount(
                'appointments'
            );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Account suspended successfully.',

            'data' => [
                'user' =>
                    (
                        new AdminUserResource(
                            $managedUser
                        )
                    )->resolve(
                        $request
                    ),
            ],
        ]);
    }

    /**
     * Reactivate a Customer or Shop Owner.
     */
    public function reactivate(
        Request $request,
        string $user
    ): JsonResponse {
        $managedUser =
            $this
                ->userManagement
                ->reactivate(
                    (int) $user
                );

        $managedUser
            ->loadCount(
                'appointments'
            );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Account reactivated successfully.',

            'data' => [
                'user' =>
                    (
                        new AdminUserResource(
                            $managedUser
                        )
                    )->resolve(
                        $request
                    ),
            ],
        ]);
    }

    /**
     * @return Builder<User>
     */
    private function managedUsers(): Builder
    {
        return User::query()
            ->whereIn(
                'role',
                [
                    UserRole::Customer->value,
                    UserRole::ShopOwner->value,
                ]
            );
    }
}