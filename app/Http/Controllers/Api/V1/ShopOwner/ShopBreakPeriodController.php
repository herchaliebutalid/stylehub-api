<?php

namespace App\Http\Controllers\Api\V1\ShopOwner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShopOwner\StoreShopBreakPeriodRequest;
use App\Http\Requests\Api\V1\ShopOwner\UpdateShopBreakPeriodRequest;
use App\Http\Resources\Api\V1\ShopBreakPeriodResource;
use App\Models\Shop;
use App\Models\ShopBreakPeriod;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ShopBreakPeriodController extends Controller
{
    /**
     * List break periods belonging only to the authenticated owner's shop.
     */
    public function index(
        Request $request
    ): JsonResponse {
        $breakPeriods = $this
            ->authenticatedShop($request)
            ->breakPeriods()
            ->ordered()
            ->get();

        return response()->json([
            'success' => true,

            'data' => [
                'break_periods' =>
                    ShopBreakPeriodResource::collection(
                        $breakPeriods
                    )->resolve($request),
            ],
        ]);
    }

    /**
     * Create one recurring weekly break for the authenticated owner's shop.
     */
    public function store(
        StoreShopBreakPeriodRequest $request
    ): JsonResponse {
        $shop = $this->authenticatedShop(
            $request
        );

        $validated = $request->validated();

        try {
            $breakPeriod = DB::transaction(
                function () use (
                    $shop,
                    $validated
                ): ShopBreakPeriod {
                    $lockedShop = Shop::query()
                        ->whereKey($shop->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $this->ensurePeriodDoesNotOverlap(
                        $lockedShop,
                        (int) $validated['day_of_week'],
                        $validated['starts_at'],
                        $validated['ends_at']
                    );

                    return $lockedShop
                        ->breakPeriods()
                        ->create([
                            'day_of_week' =>
                                $validated['day_of_week'],

                            'starts_at' =>
                                $validated['starts_at'],

                            'ends_at' =>
                                $validated['ends_at'],

                            'label' =>
                                $validated['label'] ?? null,
                        ]);
                }
            );
        } catch (UniqueConstraintViolationException) {
            $this->throwOverlapValidationError();
        }

        return response()->json([
            'success' => true,

            'message' =>
                'Break period added successfully.',

            'data' => [
                'break_period' =>
                    (new ShopBreakPeriodResource(
                        $breakPeriod
                    ))->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Replace one break period owned by the authenticated shop.
     */
    public function update(
        UpdateShopBreakPeriodRequest $request,
        string $breakPeriod
    ): JsonResponse {
        $shop = $this->authenticatedShop(
            $request
        );

        $ownedBreakPeriod = $this->ownedBreakPeriod(
            $shop,
            (int) $breakPeriod
        );

        $validated = $request->validated();

        try {
            $updatedBreakPeriod = DB::transaction(
                function () use (
                    $shop,
                    $ownedBreakPeriod,
                    $validated
                ): ShopBreakPeriod {
                    $lockedShop = Shop::query()
                        ->whereKey($shop->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $lockedBreakPeriod = $lockedShop
                        ->breakPeriods()
                        ->whereKey($ownedBreakPeriod->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $this->ensurePeriodDoesNotOverlap(
                        $lockedShop,
                        (int) $validated['day_of_week'],
                        $validated['starts_at'],
                        $validated['ends_at'],
                        $lockedBreakPeriod->id
                    );

                    $lockedBreakPeriod->day_of_week =
                        $validated['day_of_week'];

                    $lockedBreakPeriod->starts_at =
                        $validated['starts_at'];

                    $lockedBreakPeriod->ends_at =
                        $validated['ends_at'];

                    $lockedBreakPeriod->label =
                        $validated['label'] ?? null;

                    $lockedBreakPeriod->save();

                    return $lockedBreakPeriod->fresh();
                }
            );
        } catch (UniqueConstraintViolationException) {
            $this->throwOverlapValidationError();
        }

        return response()->json([
            'success' => true,

            'message' =>
                'Break period updated successfully.',

            'data' => [
                'break_period' =>
                    (new ShopBreakPeriodResource(
                        $updatedBreakPeriod
                    ))->resolve($request),
            ],
        ]);
    }

    /**
     * Delete one break period belonging to the authenticated owner's shop.
     */
    public function destroy(
        Request $request,
        string $breakPeriod
    ): JsonResponse {
        $shop = $this->authenticatedShop(
            $request
        );

        $ownedBreakPeriod = $this->ownedBreakPeriod(
            $shop,
            (int) $breakPeriod
        );

        DB::transaction(
            function () use (
                $shop,
                $ownedBreakPeriod
            ): void {
                $lockedShop = Shop::query()
                    ->whereKey($shop->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedShop
                    ->breakPeriods()
                    ->whereKey($ownedBreakPeriod->id)
                    ->lockForUpdate()
                    ->firstOrFail()
                    ->delete();
            }
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Break period removed successfully.',
        ]);
    }

    private function authenticatedShop(
        Request $request
    ): Shop {
        return $request->user()
            ->shop()
            ->firstOrFail();
    }

    private function ownedBreakPeriod(
        Shop $shop,
        int $breakPeriodId
    ): ShopBreakPeriod {
        return $shop
            ->breakPeriods()
            ->whereKey($breakPeriodId)
            ->firstOrFail();
    }

    /**
     * Prevent overlapping recurring break periods for the same shop/day.
     */
    private function ensurePeriodDoesNotOverlap(
        Shop $shop,
        int $dayOfWeek,
        string $startsAt,
        string $endsAt,
        ?int $ignoreBreakPeriodId = null
    ): void {
        $query = $shop
            ->breakPeriods()
            ->where(
                'day_of_week',
                $dayOfWeek
            )
            ->where(
                'starts_at',
                '<',
                $this->databaseTime($endsAt)
            )
            ->where(
                'ends_at',
                '>',
                $this->databaseTime($startsAt)
            );

        if ($ignoreBreakPeriodId !== null) {
            $query->whereKeyNot(
                $ignoreBreakPeriodId
            );
        }

        if ($query->exists()) {
            $this->throwOverlapValidationError();
        }
    }

    private function databaseTime(
        string $time
    ): string {
        return strlen($time) === 5
            ? $time.':00'
            : $time;
    }

    private function throwOverlapValidationError(): never
    {
        throw ValidationException::withMessages([
            'starts_at' =>
                'This break overlaps an existing break period for that day.',
        ]);
    }
}