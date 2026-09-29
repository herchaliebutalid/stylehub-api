<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\BrowsePromosRequest;
use App\Http\Resources\Api\V1\CustomerPromoResource;
use App\Models\Promo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromoDiscoveryController extends Controller
{
    /**
     * Browse promotions that are currently visible to customers.
     */
    public function index(
        BrowsePromosRequest $request
    ): JsonResponse {
        $validated =
            $request->validated();

        $query =
            $this->customerVisiblePromoQuery()
                ->with([
                    'shop',
                    'service',
                ]);

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if (
            isset($validated['search'])
            && $validated['search'] !== ''
        ) {
            $search =
                $validated['search'];

            $query->where(
                function (
                    Builder $query
                ) use ($search): void {
                    $pattern =
                        '%'.$search.'%';

                    $query
                        ->where(
                            'title',
                            'like',
                            $pattern
                        )
                        ->orWhere(
                            'description',
                            'like',
                            $pattern
                        )
                        ->orWhereHas(
                            'shop',
                            function (
                                Builder $shopQuery
                            ) use (
                                $pattern
                            ): void {
                                $shopQuery
                                    ->where(
                                        'name',
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
                        )
                        ->orWhereHas(
                            'service',
                            function (
                                Builder $serviceQuery
                            ) use (
                                $pattern
                            ): void {
                                $serviceQuery->where(
                                    'name',
                                    'like',
                                    $pattern
                                );
                            }
                        );
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Optional Shop Filter
        |--------------------------------------------------------------------------
        |
        | Visibility restrictions are already applied before this filter.
        | Passing a pending/rejected/hidden shop therefore simply exposes no
        | promotions from that shop.
        |
        */

        if (
            isset(
                $validated['shop_id']
            )
        ) {
            $query->where(
                'shop_id',
                $validated['shop_id']
            );
        }

        $perPage =
            (int) (
                $validated['per_page']
                ?? 20
            );

        $promos =
            $query
                ->orderBy(
                    'ends_at'
                )
                ->orderByDesc(
                    'created_at'
                )
                ->paginate(
                    $perPage
                );

        return response()->json([
            'success' => true,

            'data' => [
                'promos' =>
                    CustomerPromoResource::collection(
                        collect(
                            $promos->items()
                        )
                    )->resolve($request),

                'pagination' => [
                    'current_page' =>
                        $promos->currentPage(),

                    'per_page' =>
                        $promos->perPage(),

                    'total' =>
                        $promos->total(),

                    'last_page' =>
                        $promos->lastPage(),

                    'has_more_pages' =>
                        $promos->hasMorePages(),
                ],
            ],
        ]);
    }

    /**
     * Show one customer-visible promotion.
     *
     * Hidden, expired, disabled or otherwise ineligible promotions
     * deliberately return 404.
     */
    public function show(
        Request $request,
        string $promo
    ): JsonResponse {
        $visiblePromo =
            $this->customerVisiblePromoQuery()
                ->with([
                    'shop',
                    'service',
                ])
                ->whereKey(
                    $promo
                )
                ->firstOrFail();

        return response()->json([
            'success' => true,

            'data' => [
                'promo' => (
                    new CustomerPromoResource(
                        $visiblePromo
                    )
                )->resolve($request),
            ],
        ]);
    }

    /**
     * Central customer promotion visibility query.
     */
    private function customerVisiblePromoQuery(): Builder
    {
        return Promo::query()
            ->currentlyActive()
            ->visibleToCustomers();
    }
}