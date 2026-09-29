<?php

namespace App\Services\Media;

use App\Models\Service;
use App\Models\Shop;
use App\Models\ShopGalleryImage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ShopMediaService
{
    /**
     * Return this shop's gallery in stable display order.
     *
     * @return Collection<int, ShopGalleryImage>
     */
    public function galleryImages(
        Shop $shop
    ): Collection {
        return $shop
            ->galleryImages()
            ->ordered()
            ->get();
    }

    /**
     * Store a new Shop Gallery image.
     *
     * The physical file is stored before the DB insert. If anything fails
     * during the DB transaction, the new file is deleted immediately.
     */
    public function addGalleryImage(
        Shop $shop,
        UploadedFile $image
    ): ShopGalleryImage {
        $storedPath =
            $this->storeUploadedFile(
                $image,
                $this->galleryDirectory(
                    $shop
                )
            );

        try {
            return DB::transaction(
                function () use (
                    $shop,
                    $storedPath
                ): ShopGalleryImage {
                    /*
                    |--------------------------------------------------------------------------
                    | Lock Shop
                    |--------------------------------------------------------------------------
                    |
                    | Serializes concurrent uploads so two requests cannot both bypass
                    | the maximum-image check.
                    |
                    */

                    $lockedShop =
                        Shop::query()
                            ->whereKey(
                                $shop->id
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $existingCount =
                        $lockedShop
                            ->galleryImages()
                            ->count();

                    if (
                        $existingCount >=
                        $this->maxGalleryImages()
                    ) {
                        throw ValidationException::withMessages([
                            'image' =>
                                sprintf(
                                    'A shop gallery may contain at most %d images.',
                                    $this->maxGalleryImages()
                                ),
                        ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Determine Next Position
                    |--------------------------------------------------------------------------
                    */

                    $maximumPosition =
                        $lockedShop
                            ->galleryImages()
                            ->max(
                                'position'
                            );

                    /*
                    |--------------------------------------------------------------------------
                    | Fully Guarded Model
                    |--------------------------------------------------------------------------
                    |
                    | ShopGalleryImage is fully guarded, so trusted fields are assigned
                    | explicitly rather than through mass assignment.
                    |
                    */

                    $galleryImage =
                        new ShopGalleryImage();

                    $galleryImage
                        ->shop_id =
                            $lockedShop->id;

                    $galleryImage
                        ->image_path =
                            $storedPath;

                    $galleryImage
                        ->position =
                            $existingCount ===
                            0
                                ? 0
                                : (
                                    (int) $maximumPosition
                                    +
                                    1
                                );

                    $galleryImage
                        ->save();

                    return $galleryImage
                        ->fresh();
                }
            );
        } catch (
            Throwable $exception
        ) {
            /*
            |--------------------------------------------------------------------------
            | Database Failure Cleanup
            |--------------------------------------------------------------------------
            */

            $this->deleteStoredPathQuietly(
                $storedPath
            );

            throw $exception;
        }
    }

    /**
     * Delete one gallery image belonging only to the supplied shop.
     */
    public function deleteGalleryImage(
        Shop $shop,
        int $galleryImageId
    ): void {
        $path =
            DB::transaction(
                function () use (
                    $shop,
                    $galleryImageId
                ): string {
                    $lockedShop =
                        Shop::query()
                            ->whereKey(
                                $shop->id
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    /*
                    |--------------------------------------------------------------------------
                    | Tenant Isolation
                    |--------------------------------------------------------------------------
                    |
                    | The image is resolved through this shop's relationship.
                    |
                    */

                    $galleryImage =
                        $lockedShop
                            ->galleryImages()
                            ->whereKey(
                                $galleryImageId
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $path =
                        $galleryImage
                            ->image_path;

                    $galleryImage
                        ->delete();

                    return $path;
                }
            );

        /*
        |--------------------------------------------------------------------------
        | Physical File Cleanup
        |--------------------------------------------------------------------------
        |
        | The DB mutation succeeds first. Filesystem cleanup is then attempted
        | without risking rollback of the user's completed media operation.
        |
        */

        $this->deleteStoredPathQuietly(
            $path
        );
    }

    /**
     * Replace the complete gallery order.
     *
     * @param list<int> $imageIds
     * @return Collection<int, ShopGalleryImage>
     */
    public function reorderGallery(
        Shop $shop,
        array $imageIds
    ): Collection {
        DB::transaction(
            function () use (
                $shop,
                $imageIds
            ): void {
                $lockedShop =
                    Shop::query()
                        ->whereKey(
                            $shop->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $images =
                    $lockedShop
                        ->galleryImages()
                        ->orderBy(
                            'id'
                        )
                        ->lockForUpdate()
                        ->get();

                $currentIds =
                    $images
                        ->pluck(
                            'id'
                        )
                        ->map(
                            fn (
                                mixed $id
                            ): int =>
                                (int) $id
                        )
                        ->all();

                $requestedIds =
                    array_map(
                        static fn (
                            mixed $id
                        ): int =>
                            (int) $id,
                        $imageIds
                    );

                $sortedCurrentIds =
                    $currentIds;

                $sortedRequestedIds =
                    $requestedIds;

                sort(
                    $sortedCurrentIds
                );

                sort(
                    $sortedRequestedIds
                );

                /*
                |--------------------------------------------------------------------------
                | Exact Set Required
                |--------------------------------------------------------------------------
                |
                | The request must contain every current gallery image exactly once.
                | Foreign IDs, missing IDs or additional IDs are rejected.
                |
                */

                if (
                    $sortedCurrentIds !==
                    $sortedRequestedIds
                ) {
                    throw ValidationException::withMessages([
                        'image_ids' =>
                            'The image order must contain every image in your gallery exactly once.',
                    ]);
                }

                $imagesById =
                    $images->keyBy(
                        'id'
                    );

                foreach (
                    $requestedIds as
                    $position =>
                    $imageId
                ) {
                    /** @var ShopGalleryImage $galleryImage */
                    $galleryImage =
                        $imagesById->get(
                            $imageId
                        );

                    $galleryImage
                        ->position =
                            $position;

                    $galleryImage
                        ->save();
                }
            }
        );

        return $this->galleryImages(
            $shop->fresh()
        );
    }

    /**
     * Upload or replace one service display image.
     */
    public function replaceServiceImage(
        Shop $shop,
        Service $service,
        UploadedFile $image
    ): Service {
        /*
        |--------------------------------------------------------------------------
        | Store New Image First
        |--------------------------------------------------------------------------
        */

        $storedPath =
            $this->storeUploadedFile(
                $image,
                $this->serviceDirectory(
                    $shop,
                    $service
                )
            );

        $oldPath =
            null;

        try {
            $updatedService =
                DB::transaction(
                    function () use (
                        $shop,
                        $service,
                        $storedPath,
                        &$oldPath
                    ): Service {
                        $lockedShop =
                            Shop::query()
                                ->whereKey(
                                    $shop->id
                                )
                                ->lockForUpdate()
                                ->firstOrFail();

                        /*
                        |--------------------------------------------------------------------------
                        | Service Tenant Isolation
                        |--------------------------------------------------------------------------
                        */

                        $ownedService =
                            $lockedShop
                                ->services()
                                ->whereKey(
                                    $service->id
                                )
                                ->lockForUpdate()
                                ->firstOrFail();

                        $oldPath =
                            $ownedService
                                ->image_path;

                        $ownedService
                            ->image_path =
                                $storedPath;

                        $ownedService
                            ->save();

                        return $ownedService
                            ->fresh();
                    }
                );
        } catch (
            Throwable $exception
        ) {
            /*
            |--------------------------------------------------------------------------
            | New File Cleanup On DB Failure
            |--------------------------------------------------------------------------
            */

            $this->deleteStoredPathQuietly(
                $storedPath
            );

            throw $exception;
        }

        /*
        |--------------------------------------------------------------------------
        | Remove Replaced Image
        |--------------------------------------------------------------------------
        |
        | The old file is removed only after the new file and DB reference
        | are safely established.
        |
        */

        if (
            is_string(
                $oldPath
            )
            &&
            $oldPath !==
            ''
            &&
            $oldPath !==
            $storedPath
        ) {
            $this->deleteStoredPathQuietly(
                $oldPath
            );
        }

        return $updatedService;
    }

    /**
     * Remove a service display image.
     *
     * Calling this on a service that already has no image is safe and
     * idempotent.
     */
    public function deleteServiceImage(
        Shop $shop,
        Service $service
    ): Service {
        $oldPath =
            null;

        $updatedService =
            DB::transaction(
                function () use (
                    $shop,
                    $service,
                    &$oldPath
                ): Service {
                    $lockedShop =
                        Shop::query()
                            ->whereKey(
                                $shop->id
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $ownedService =
                        $lockedShop
                            ->services()
                            ->whereKey(
                                $service->id
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $oldPath =
                        $ownedService
                            ->image_path;

                    if (
                        $oldPath !==
                        null
                    ) {
                        $ownedService
                            ->image_path =
                                null;

                        $ownedService
                            ->save();
                    }

                    return $ownedService
                        ->fresh();
                }
            );

        if (
            is_string(
                $oldPath
            )
            &&
            $oldPath !==
            ''
        ) {
            $this->deleteStoredPathQuietly(
                $oldPath
            );
        }

        return $updatedService;
    }

    /**
     * Store an uploaded file using Laravel-generated hashed filenames.
     */
    private function storeUploadedFile(
        UploadedFile $file,
        string $directory
    ): string {
        /*
         * UploadedFile::store() generates a server-side hashed filename.
         * The original client filename is never trusted as the storage name.
         */

        $path =
            $file->store(
                $directory,
                $this->diskName()
            );

        if (
            ! is_string(
                $path
            )
            ||
            trim(
                $path
            ) ===
            ''
        ) {
            throw new RuntimeException(
                'Unable to store the uploaded image.'
            );
        }

        return $path;
    }

    /**
     * Best-effort cleanup without replacing the original business exception.
     */
    private function deleteStoredPathQuietly(
        ?string $path
    ): void {
        if (
            ! is_string(
                $path
            )
            ||
            trim(
                $path
            ) ===
            ''
        ) {
            return;
        }

        try {
            $disk =
                Storage::disk(
                    $this->diskName()
                );

            if (
                $disk->exists(
                    $path
                )
                &&
                ! $disk->delete(
                    $path
                )
            ) {
                report(
                    new RuntimeException(
                        'Unable to delete a stored shop media file.'
                    )
                );
            }
        } catch (
            Throwable $exception
        ) {
            report(
                $exception
            );
        }
    }

    private function diskName(): string
    {
        return (string) config(
            'shop-media.disk',
            'public'
        );
    }

    private function maxGalleryImages(): int
    {
        return max(
            1,
            min(
                50,
                (int) config(
                    'shop-media.gallery.max_images',
                    10
                )
            )
        );
    }

    private function galleryDirectory(
        Shop $shop
    ): string {
        return trim(
            (string) config(
                'shop-media.gallery.directory',
                'shop-gallery'
            ),
            '/'
        )
        .'/'
        .$shop->id;
    }

    private function serviceDirectory(
        Shop $shop,
        Service $service
    ): string {
        return trim(
            (string) config(
                'shop-media.services.directory',
                'service-images'
            ),
            '/'
        )
        .'/'
        .$shop->id
        .'/'
        .$service->id;
    }
}