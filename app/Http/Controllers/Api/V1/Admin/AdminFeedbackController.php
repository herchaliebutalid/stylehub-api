<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\BrowseAdminFeedbackRequest;
use App\Http\Resources\Api\V1\AdminFeedbackResource;
use App\Models\Feedback;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminFeedbackController extends Controller
{
    /**
     * Browse feedback across the entire StyleHub system.
     *
     * Administrator web only.
     *
     * This endpoint is intentionally read-only.
     */
    public function index(
        BrowseAdminFeedbackRequest $request
    ): JsonResponse {
        $validated =
            $request->validated();

        $query =
            $this->baseQuery();

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        |
        | Searchable fields:
        |
        | - Feedback comment
        | - Customer name
        | - Customer email
        | - Shop name
        |
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
                            'comment',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhereHas(
                            'customer',
                            function (
                                Builder $customerQuery
                            ) use (
                                $search
                            ): void {
                                $customerQuery
                                    ->withTrashed()
                                    ->where(
                                        function (
                                            Builder $customerQuery
                                        ) use (
                                            $search
                                        ): void {
                                            $customerQuery
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
                                                );
                                        }
                                    );
                            }
                        )
                        ->orWhereHas(
                            'shop',
                            function (
                                Builder $shopQuery
                            ) use (
                                $search
                            ): void {
                                $shopQuery
                                    ->withTrashed()
                                    ->where(
                                        'name',
                                        'like',
                                        "%{$search}%"
                                    );
                            }
                        );
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Rating Filter
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $validated['rating']
            )
        ) {
            $query->where(
                'rating',
                $validated['rating']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Shop Filter
        |--------------------------------------------------------------------------
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

        /*
        |--------------------------------------------------------------------------
        | Customer Filter
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $validated['customer_id']
            )
        ) {
            $query->where(
                'customer_id',
                $validated['customer_id']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Feedback Submission Date Range
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $validated['date_from']
            )
        ) {
            $query->whereDate(
                'created_at',
                '>=',
                $validated['date_from']
            );
        }

        if (
            isset(
                $validated['date_to']
            )
        ) {
            $query->whereDate(
                'created_at',
                '<=',
                $validated['date_to']
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

        $feedback =
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
                'feedback' =>
                    AdminFeedbackResource::collection(
                        collect(
                            $feedback->items()
                        )
                    )->resolve(
                        $request
                    ),

                'pagination' => [
                    'current_page' =>
                        $feedback
                            ->currentPage(),

                    'per_page' =>
                        $feedback
                            ->perPage(),

                    'total' =>
                        $feedback
                            ->total(),

                    'last_page' =>
                        $feedback
                            ->lastPage(),

                    'has_more_pages' =>
                        $feedback
                            ->hasMorePages(),
                ],
            ],
        ]);
    }

    /**
     * View one feedback record.
     *
     * Administrator web only.
     *
     * No update or delete action exists.
     */
    public function show(
        Request $request,
        string $feedback
    ): JsonResponse {
        $feedbackModel =
            $this
                ->baseQuery()
                ->whereKey(
                    $feedback
                )
                ->firstOrFail();

        return response()->json([
            'success' =>
                true,

            'data' => [
                'feedback' =>
                    (
                        new AdminFeedbackResource(
                            $feedbackModel
                        )
                    )->resolve(
                        $request
                    ),
            ],
        ]);
    }

    /**
     * Build the Administrator feedback monitoring query.
     *
     * Customer and Shop are loaded with soft-deleted records so
     * historical feedback remains meaningful after account/shop
     * deletion.
     *
     * Eager-loading callbacks intentionally have no Builder type hint
     * because Laravel supplies the relationship object.
     *
     * @return Builder<Feedback>
     */
    private function baseQuery(): Builder
    {
        return Feedback::query()
            ->with([
                'customer' =>
                    function (
                        $query
                    ): void {
                        $query
                            ->withTrashed();
                    },

                'shop' =>
                    function (
                        $query
                    ): void {
                        $query
                            ->withTrashed();
                    },

                'appointment',
            ]);
    }
}