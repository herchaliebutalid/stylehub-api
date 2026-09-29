<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\NearbyShopsRequest;
use App\Http\Resources\Api\V1\CustomerShopResource;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class NearbyShopDiscoveryController extends Controller
{
    private const EARTH_RADIUS_KM = 6371.0088;

    /**
     * Return customer-visible located shops ordered by real distance.
     *
     * The customer's coordinates are used only for this request. They are
     * never persisted to the database by this endpoint.
     */
    public function index(
        NearbyShopsRequest $request
    ): JsonResponse {
        $validated =
            $request->validated();

        $latitude =
            (float) $validated['latitude'];

        $longitude =
            (float) $validated['longitude'];

        $radiusKm =
            (float) (
                $validated['radius_km']
                ?? 25
            );

        $limit =
            (int) (
                $validated['limit']
                ?? 20
            );

        $query =
            Shop::query()
                ->visibleToCustomers()
                ->located()

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
        | Fast Candidate Filter
        |--------------------------------------------------------------------------
        |
        | First narrow the database query to a geographic bounding box.
        |
        | We then perform the exact great-circle distance calculation below.
        |
        */

        $this->applyBoundingBox(
            $query,
            $latitude,
            $longitude,
            $radiusKm
        );

        /*
        |--------------------------------------------------------------------------
        | Precise Distance
        |--------------------------------------------------------------------------
        */

        $nearbyShops =
            $query
                ->get()

                ->map(
                    function (
                        Shop $shop
                    ) use (
                        $latitude,
                        $longitude
                    ): array {
                        $distanceKm =
                            $this->distanceKm(
                                $latitude,
                                $longitude,
                                (float) $shop->latitude,
                                (float) $shop->longitude
                            );

                        return [
                            'shop' =>
                                $shop,

                            'distance_km' =>
                                $distanceKm,
                        ];
                    }
                )

                /*
                |--------------------------------------------------------------------------
                | Precise Radius Check
                |--------------------------------------------------------------------------
                */

                ->filter(
                    fn (array $item): bool =>
                        $item['distance_km']
                            <= $radiusKm
                )

                /*
                |--------------------------------------------------------------------------
                | Nearest First
                |--------------------------------------------------------------------------
                */

                ->sort(
                    function (
                        array $left,
                        array $right
                    ): int {
                        $distanceComparison =
                            $left['distance_km']
                                <=>
                            $right['distance_km'];

                        if (
                            $distanceComparison !== 0
                        ) {
                            return $distanceComparison;
                        }

                        /*
                         * Deterministic ordering when two shops have
                         * effectively identical distance.
                         */

                        return strcasecmp(
                            $left['shop']->name,
                            $right['shop']->name
                        );
                    }
                )

                ->take(
                    $limit
                )

                ->values();

        /*
        |--------------------------------------------------------------------------
        | Customer-Safe Resource
        |--------------------------------------------------------------------------
        */

        $shops =
            $nearbyShops
                ->map(
                    function (
                        array $item
                    ) use (
                        $request
                    ): array {
                        $resource =
                            (
                                new CustomerShopResource(
                                    $item['shop']
                                )
                            )->resolve(
                                $request
                            );

                        /*
                         * Distance is returned in kilometers to two decimals.
                         */

                        $resource['distance_km'] =
                            round(
                                $item['distance_km'],
                                2
                            );

                        return $resource;
                    }
                )
                ->all();

        return response()->json([
            'success' =>
                true,

            'data' => [
                'shops' =>
                    $shops,

                'search' => [
                    'radius_km' =>
                        $radiusKm,

                    'limit' =>
                        $limit,

                    'results_count' =>
                        count(
                            $shops
                        ),
                ],
            ],
        ]);
    }

    /**
     * Apply a database-friendly geographic bounding box before the precise
     * Haversine calculation runs in PHP.
     */
    private function applyBoundingBox(
        Builder $query,
        float $latitude,
        float $longitude,
        float $radiusKm
    ): void {
        /*
        |--------------------------------------------------------------------------
        | Latitude Bounding Box
        |--------------------------------------------------------------------------
        */

        $latitudeDelta =
            $radiusKm
            / 111.32;

        $minimumLatitude =
            max(
                -90.0,
                $latitude
                    - $latitudeDelta
            );

        $maximumLatitude =
            min(
                90.0,
                $latitude
                    + $latitudeDelta
            );

        $query->whereBetween(
            'latitude',
            [
                $minimumLatitude,
                $maximumLatitude,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Longitude Bounding Box
        |--------------------------------------------------------------------------
        */

        $cosineLatitude =
            abs(
                cos(
                    deg2rad(
                        $latitude
                    )
                )
            );

        /*
         * Longitude becomes meaningless extremely close to the poles.
         * In that rare case, latitude filtering is enough for our
         * candidate step.
         */

        if (
            $cosineLatitude
            < 0.000001
        ) {
            return;
        }

        $longitudeDelta =
            min(
                180.0,

                $radiusKm
                /
                (
                    111.32
                    * $cosineLatitude
                )
            );

        if (
            $longitudeDelta
            >= 180.0
        ) {
            return;
        }

        $minimumLongitude =
            $longitude
            - $longitudeDelta;

        $maximumLongitude =
            $longitude
            + $longitudeDelta;

        /*
        |--------------------------------------------------------------------------
        | International Date Line
        |--------------------------------------------------------------------------
        */

        if (
            $minimumLongitude
            < -180.0
        ) {
            $wrappedMinimum =
                $minimumLongitude
                + 360.0;

            $query->where(
                function (
                    Builder $query
                ) use (
                    $wrappedMinimum,
                    $maximumLongitude
                ): void {
                    $query
                        ->where(
                            'longitude',
                            '>=',
                            $wrappedMinimum
                        )

                        ->orWhere(
                            'longitude',
                            '<=',
                            $maximumLongitude
                        );
                }
            );

            return;
        }

        if (
            $maximumLongitude
            > 180.0
        ) {
            $wrappedMaximum =
                $maximumLongitude
                - 360.0;

            $query->where(
                function (
                    Builder $query
                ) use (
                    $minimumLongitude,
                    $wrappedMaximum
                ): void {
                    $query
                        ->where(
                            'longitude',
                            '>=',
                            $minimumLongitude
                        )

                        ->orWhere(
                            'longitude',
                            '<=',
                            $wrappedMaximum
                        );
                }
            );

            return;
        }

        $query->whereBetween(
            'longitude',
            [
                $minimumLongitude,
                $maximumLongitude,
            ]
        );
    }

    /**
     * Calculate great-circle distance using the Haversine formula.
     */
    private function distanceKm(
        float $fromLatitude,
        float $fromLongitude,
        float $toLatitude,
        float $toLongitude
    ): float {
        $fromLatitudeRadians =
            deg2rad(
                $fromLatitude
            );

        $toLatitudeRadians =
            deg2rad(
                $toLatitude
            );

        $latitudeDelta =
            deg2rad(
                $toLatitude
                - $fromLatitude
            );

        $longitudeDelta =
            deg2rad(
                $toLongitude
                - $fromLongitude
            );

        $a =
            sin(
                $latitudeDelta
                / 2
            ) ** 2

            +

            cos(
                $fromLatitudeRadians
            )

            * cos(
                $toLatitudeRadians
            )

            * sin(
                $longitudeDelta
                / 2
            ) ** 2;

        /*
         * Protect atan2/sqrt from floating-point values just outside
         * the mathematical 0..1 range.
         */

        $a =
            min(
                1.0,

                max(
                    0.0,
                    $a
                )
            );

        $angularDistance =
            2
            * atan2(
                sqrt(
                    $a
                ),

                sqrt(
                    1
                    - $a
                )
            );

        return self::EARTH_RADIUS_KM
            * $angularDistance;
    }
}