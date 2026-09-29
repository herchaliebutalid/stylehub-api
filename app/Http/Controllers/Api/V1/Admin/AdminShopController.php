<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\BrowseAdminShopsRequest;
use App\Http\Resources\Api\V1\AdminShopDetailResource;
use App\Http\Resources\Api\V1\AdminShopResource;
use App\Models\Appointment;
use App\Models\Feedback;
use App\Models\Promo;
use App\Models\Service;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminShopController extends Controller
{
    /**
     * Browse all non-deleted StyleHub shops.
     *
     * Administrator web only.
     */
    public function index(
        BrowseAdminShopsRequest $request
    ): JsonResponse {
        $validated =
            $request->validated();

        $query =
            Shop::query()
                ->with([
                    'owner',
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
                            'name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'email',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'address',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'city',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'province',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Approval Status
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $validated[
                    'approval_status'
                ]
            )
        ) {
            $query->where(
                'approval_status',
                $validated[
                    'approval_status'
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Administrative Status
        |--------------------------------------------------------------------------
        |
        | Independent from approval_status and availability_status.
        |
        */

        if (
            isset(
                $validated[
                    'administrative_status'
                ]
            )
        ) {
            $query->where(
                'administrative_status',
                $validated[
                    'administrative_status'
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Shop Type
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $validated['type']
            )
        ) {
            $query->where(
                'type',
                $validated['type']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Availability
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $validated[
                    'availability_status'
                ]
            )
        ) {
            $query->where(
                'availability_status',
                $validated[
                    'availability_status'
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Location
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $validated['city']
            )
        ) {
            $query->where(
                'city',
                'like',
                '%'
                .$validated['city']
                .'%'
            );
        }

        if (
            isset(
                $validated['province']
            )
        ) {
            $query->where(
                'province',
                'like',
                '%'
                .$validated['province']
                .'%'
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

        $shops =
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
                'shops' =>
                    AdminShopResource::collection(
                        collect(
                            $shops->items()
                        )
                    )->resolve(
                        $request
                    ),

                'pagination' => [
                    'current_page' =>
                        $shops->currentPage(),

                    'per_page' =>
                        $shops->perPage(),

                    'total' =>
                        $shops->total(),

                    'last_page' =>
                        $shops->lastPage(),

                    'has_more_pages' =>
                        $shops->hasMorePages(),
                ],
            ],
        ]);
    }

    /**
     * Display one shop with safe Administrator-only details.
     */
    public function show(
        Request $request,
        string $shop
    ): JsonResponse {
        $shopModel =
            Shop::query()
                ->with([
                    'owner',
                    'reviewer',
                    'suspender',
                ])
                ->whereKey(
                    $shop
                )
                ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Operational Statistics
        |--------------------------------------------------------------------------
        |
        | These counts intentionally use server-derived shop_id values.
        |
        */

        $shopModel->setAttribute(
            'admin_statistics',
            [
                'services' =>
                    Service::query()
                        ->where(
                            'shop_id',
                            $shopModel->id
                        )
                        ->count(),

                'active_services' =>
                    Service::query()
                        ->where(
                            'shop_id',
                            $shopModel->id
                        )
                        ->where(
                            'is_active',
                            true
                        )
                        ->count(),

                'appointments' =>
                    Appointment::query()
                        ->where(
                            'shop_id',
                            $shopModel->id
                        )
                        ->count(),

                'feedback' =>
                    Feedback::query()
                        ->where(
                            'shop_id',
                            $shopModel->id
                        )
                        ->count(),

                'promos' =>
                    Promo::query()
                        ->where(
                            'shop_id',
                            $shopModel->id
                        )
                        ->count(),

                'active_promos' =>
                    Promo::query()
                        ->where(
                            'shop_id',
                            $shopModel->id
                        )
                        ->where(
                            'is_active',
                            true
                        )
                        ->count(),
            ]
        );

        return response()->json([
            'success' =>
                true,

            'data' => [
                'shop' =>
                    (
                        new AdminShopDetailResource(
                            $shopModel
                        )
                    )->resolve(
                        $request
                    ),
            ],
        ]);
    }
}