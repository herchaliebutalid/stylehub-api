<?php

namespace App\Http\Controllers\Api\V1\ShopOwner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShopOwner\StoreShopBlockedDateRequest;
use App\Http\Requests\Api\V1\ShopOwner\UpdateShopBlockedDateRequest;
use App\Http\Resources\Api\V1\ShopBlockedDateResource;
use App\Models\Shop;
use App\Models\ShopBlockedDate;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ShopBlockedDateController extends Controller
{
    /**
     * List blocked dates belonging only to the authenticated owner's shop.
     */
    public function index(
        Request $request
    ): JsonResponse {
        $blockedDates = $this
            ->authenticatedShop($request)
            ->blockedDates()
            ->ordered()
            ->get();

        return response()->json([
            'success' => true,

            'data' => [
                'blocked_dates' =>
                    ShopBlockedDateResource::collection(
                        $blockedDates
                    )->resolve($request),
            ],
        ]);
    }

    /**
     * Create one whole-day closure for the authenticated owner's shop.
     */
    public function store(
        StoreShopBlockedDateRequest $request
    ): JsonResponse {
        $shop = $this->authenticatedShop(
            $request
        );

        $validated = $request->validated();

        try {
            $blockedDate = DB::transaction(
                function () use (
                    $shop,
                    $validated
                ): ShopBlockedDate {
                    /*
                     * Lock this tenant's shop so two simultaneous blocked-date
                     * writes cannot race each other.
                     */
                    $lockedShop = Shop::query()
                        ->whereKey($shop->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $this->ensureDateIsAvailable(
                        $lockedShop,
                        $validated['blocked_date']
                    );

                    /*
                     * shop_id comes from the authenticated shop relationship,
                     * never from the request.
                     */
                    return $lockedShop
                        ->blockedDates()
                        ->create([
                            'blocked_date' =>
                                $validated['blocked_date'],

                            'reason' =>
                                $validated['reason'] ?? null,
                        ]);
                }
            );
        } catch (UniqueConstraintViolationException) {
            /*
             * Database uniqueness remains the final defense if another
             * request somehow reaches the unique constraint first.
             */
            $this->throwDuplicateDateValidationError();
        }

        return response()->json([
            'success' => true,

            'message' =>
                'Blocked date added successfully.',

            'data' => [
                'blocked_date' =>
                    (new ShopBlockedDateResource(
                        $blockedDate
                    ))->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Replace one blocked-date record owned by the authenticated shop.
     */
    public function update(
        UpdateShopBlockedDateRequest $request,
        string $blockedDate
    ): JsonResponse {
        $shop = $this->authenticatedShop(
            $request
        );

        /*
         * First resolve through the authenticated shop relationship.
         *
         * A blocked-date ID belonging to another tenant therefore behaves
         * as if it does not exist.
         */
        $ownedBlockedDate = $this->ownedBlockedDate(
            $shop,
            (int) $blockedDate
        );

        $validated = $request->validated();

        try {
            $updatedBlockedDate = DB::transaction(
                function () use (
                    $shop,
                    $ownedBlockedDate,
                    $validated
                ): ShopBlockedDate {
                    $lockedShop = Shop::query()
                        ->whereKey($shop->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $lockedBlockedDate = $lockedShop
                        ->blockedDates()
                        ->whereKey($ownedBlockedDate->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $this->ensureDateIsAvailable(
                        $lockedShop,
                        $validated['blocked_date'],
                        $lockedBlockedDate->id
                    );

                    $lockedBlockedDate->blocked_date =
                        $validated['blocked_date'];

                    $lockedBlockedDate->reason =
                        $validated['reason'] ?? null;

                    $lockedBlockedDate->save();

                    return $lockedBlockedDate->fresh();
                }
            );
        } catch (UniqueConstraintViolationException) {
            $this->throwDuplicateDateValidationError();
        }

        return response()->json([
            'success' => true,

            'message' =>
                'Blocked date updated successfully.',

            'data' => [
                'blocked_date' =>
                    (new ShopBlockedDateResource(
                        $updatedBlockedDate
                    ))->resolve($request),
            ],
        ]);
    }

    /**
     * Delete one blocked date belonging to the authenticated owner's shop.
     */
    public function destroy(
        Request $request,
        string $blockedDate
    ): JsonResponse {
        $shop = $this->authenticatedShop(
            $request
        );

        $ownedBlockedDate = $this->ownedBlockedDate(
            $shop,
            (int) $blockedDate
        );

        DB::transaction(
            function () use (
                $shop,
                $ownedBlockedDate
            ): void {
                $lockedShop = Shop::query()
                    ->whereKey($shop->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedShop
                    ->blockedDates()
                    ->whereKey($ownedBlockedDate->id)
                    ->lockForUpdate()
                    ->firstOrFail()
                    ->delete();
            }
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Blocked date removed successfully.',
        ]);
    }

    /**
     * Resolve only the authenticated owner's approved shop.
     */
    private function authenticatedShop(
        Request $request
    ): Shop {
        return $request->user()
            ->shop()
            ->firstOrFail();
    }

    /**
     * Resolve a blocked date only through the authenticated shop relationship.
     */
    private function ownedBlockedDate(
        Shop $shop,
        int $blockedDateId
    ): ShopBlockedDate {
        return $shop
            ->blockedDates()
            ->whereKey($blockedDateId)
            ->firstOrFail();
    }

    /**
     * Enforce one blocked-date record per shop and calendar date.
     */
    private function ensureDateIsAvailable(
        Shop $shop,
        string $blockedDate,
        ?int $ignoreBlockedDateId = null
    ): void {
        $query = $shop
            ->blockedDates()
            ->whereDate(
                'blocked_date',
                $blockedDate
            );

        if ($ignoreBlockedDateId !== null) {
            $query->whereKeyNot(
                $ignoreBlockedDateId
            );
        }

        if ($query->exists()) {
            $this->throwDuplicateDateValidationError();
        }
    }

    /**
     * Return a consistent safe validation response for duplicate dates.
     */
    private function throwDuplicateDateValidationError(): never
    {
        throw ValidationException::withMessages([
            'blocked_date' =>
                'This date is already blocked for your shop.',
        ]);
    }
}