<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\BrowseShopsRequest;
use App\Http\Resources\Api\V1\CustomerShopResource;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopDiscoveryController extends Controller
{
    /**
     * Browse customer-visible shops.
     */
    public function index(
        BrowseShopsRequest $request
    ): JsonResponse {
        $validated =
            $request->validated();

        $query =
            Shop::query()
                ->visibleToCustomers()

                /*
                |--------------------------------------------------------------------------
                | Shop Owner Profile Photo
                |--------------------------------------------------------------------------
                |
                | Customers only need the owner's profile-photo path here.
                |
                | We intentionally do not expose the owner's:
                |
                | - email
                | - phone number
                | - account status
                | - credentials
                | - tokens
                |
                | The CustomerShopResource decides what safe public owner
                | information is returned to the mobile application.
                |
                */

                ->with([
                    'owner:id,profile_photo_path',
                ])

                ->withCount([
                    'services as active_services_count' =>
                        function (
                            Builder $query
                        ): void {
                            $query->where(
                                'is_active',
                                true
                            );
                        },

                    /*
                     * Count only reviews that can still be verified
                     * against completed appointments.
                     */

                    'feedback' =>
                        function (
                            $query
                        ): void {
                            $query->verified();
                        },
                ])

                ->withAvg(
                    [
                        'feedback' =>
                            function (
                                $query
                            ): void {
                                $query->verified();
                            },
                    ],
                    'rating'
                );

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $validated['search']
            )
            &&
            $validated['search'] !== ''
        ) {
            $search =
                $validated['search'];

            $query->where(
                function (
                    Builder $query
                ) use (
                    $search
                ): void {
                    $pattern =
                        '%'.$search.'%';

                    $query
                        ->where(
                            'name',
                            'like',
                            $pattern
                        )
                        ->orWhere(
                            'description',
                            'like',
                            $pattern
                        )
                        ->orWhere(
                            'address',
                            'like',
                            $pattern
                        )
                        ->orWhere(
                            'city',
                            'like',
                            $pattern
                        )
                        ->orWhere(
                            'province',
                            'like',
                            $pattern
                        );
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Shop Type Filter
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $validated['type']
            )
            &&
            $validated['type'] !== ''
        ) {
            $query->where(
                'type',
                $validated['type']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | City Filter
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $validated['city']
            )
            &&
            $validated['city'] !== ''
        ) {
            $query->where(
                'city',
                $validated['city']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Province Filter
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $validated['province']
            )
            &&
            $validated['province'] !== ''
        ) {
            $query->where(
                'province',
                $validated['province']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Availability Filter
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $validated[
                    'availability_status'
                ]
            )
            &&
            $validated[
                'availability_status'
            ] !== ''
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
                ->orderBy(
                    'name'
                )
                ->paginate(
                    $perPage
                );

        return response()->json([
            'success' =>
                true,

            'data' => [
                'shops' =>
                    CustomerShopResource::collection(
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
     * Show one customer-visible shop with:
     *
     * - shop owner profile photo;
     * - ordered shop gallery;
     * - services;
     * - weekly schedule;
     * - promotions;
     * - rating summary;
     * - latest verified reviews.
     */
    public function show(
        Request $request,
        string $shop
    ): JsonResponse {
        $visibleShop =
            Shop::query()
                ->visibleToCustomers()

                ->withCount([
                    'services as active_services_count' =>
                        function (
                            Builder $query
                        ): void {
                            $query->where(
                                'is_active',
                                true
                            );
                        },

                    'feedback' =>
                        function (
                            $query
                        ): void {
                            $query->verified();
                        },
                ])

                ->withAvg(
                    [
                        'feedback' =>
                            function (
                                $query
                            ): void {
                                $query->verified();
                            },
                    ],
                    'rating'
                )

                ->with([
                    /*
                    |--------------------------------------------------------------------------
                    | Shop Owner Profile Photo
                    |--------------------------------------------------------------------------
                    |
                    | Only the fields required to display the owner's public
                    | profile picture are loaded.
                    |
                    */

                    'owner:id,profile_photo_path',

                    /*
                    |--------------------------------------------------------------------------
                    | Shop Gallery
                    |--------------------------------------------------------------------------
                    |
                    | Gallery images are loaded only for a customer-visible shop detail.
                    | The resource exposes public URLs while keeping image_path private.
                    |
                    */

                    'galleryImages' =>
                        function (
                            $query
                        ): void {
                            $query->ordered();
                        },

                    /*
                    |--------------------------------------------------------------------------
                    | Active Services
                    |--------------------------------------------------------------------------
                    */

                    'services' =>
                        function (
                            $query
                        ): void {
                            $query
                                ->where(
                                    'is_active',
                                    true
                                )
                                ->orderBy(
                                    'name'
                                );
                        },

                    /*
                    |--------------------------------------------------------------------------
                    | Schedule
                    |--------------------------------------------------------------------------
                    */

                    'schedules' =>
                        function (
                            $query
                        ): void {
                            $query->orderBy(
                                'day_of_week'
                            );
                        },

                    /*
                    |--------------------------------------------------------------------------
                    | Current Promotions
                    |--------------------------------------------------------------------------
                    */

                    'promos' =>
                        function (
                            $query
                        ): void {
                            $query
                                ->currentlyActive()
                                ->visibleToCustomers()
                                ->with([
                                    'service',
                                ])
                                ->orderBy(
                                    'ends_at'
                                );
                        },

                    /*
                    |--------------------------------------------------------------------------
                    | Latest Verified Reviews
                    |--------------------------------------------------------------------------
                    |
                    | This is safe because show() retrieves only one shop.
                    |
                    */

                    'feedback' =>
                        function (
                            $query
                        ): void {
                            $query
                                ->verified()
                                ->with([
                                    'appointment',
                                ])
                                ->orderByDesc(
                                    'created_at'
                                )
                                ->limit(5);
                        },
                ])

                ->whereKey(
                    $shop
                )

                ->firstOrFail();

        return response()->json([
            'success' =>
                true,

            'data' => [
                'shop' =>
                    (
                        new CustomerShopResource(
                            $visibleShop
                        )
                    )->resolve(
                        $request
                    ),
            ],
        ]);
    }
}