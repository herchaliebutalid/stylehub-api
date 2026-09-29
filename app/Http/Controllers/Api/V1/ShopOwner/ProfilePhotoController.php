<?php

namespace App\Http\Controllers\Api\V1\ShopOwner;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ProfilePhotoController extends Controller
{
    /**
     * Upload or replace the authenticated Shop Owner's profile photo.
     */
    public function store(
        Request $request
    ): JsonResponse {
        $user =
            $request->user();

        /*
        |--------------------------------------------------------------------------
        | Defense In Depth
        |--------------------------------------------------------------------------
        |
        | The route is already protected by Shop Owner middleware.
        | We still confirm the authenticated user's role here.
        |
        */

        if (
            $user === null
            ||
            ! $user->isShopOwner()
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'You are not authorized to perform this action.',
            ], Response::HTTP_FORBIDDEN);
        }

        /*
        |--------------------------------------------------------------------------
        | Secure Image Validation
        |--------------------------------------------------------------------------
        |
        | SVG is intentionally excluded.
        |
        | Maximum file size:
        | 5 MB
        |
        | Maximum dimensions:
        | 4096 x 4096
        |
        */

        $request->validate([
            'photo' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
                'dimensions:max_width=4096,max_height=4096',
            ],
        ]);

        $photo =
            $request->file(
                'photo'
            );

        if (
            $photo === null
        ) {
            throw new RuntimeException(
                'The profile photo upload was not received.'
            );
        }

        $oldPhotoPath =
            $user->profile_photo_path;

        $newPhotoPath =
            null;

        /*
        |--------------------------------------------------------------------------
        | Store New Photo
        |--------------------------------------------------------------------------
        |
        | We store the new image first. The old image is removed only after
        | the new file and database update succeed.
        |
        */

        try {
            $storedPath =
                $photo->store(
                    'profile-photos/'
                        .$user->getAuthIdentifier(),
                    'public'
                );

            if (
                ! is_string(
                    $storedPath
                )
                ||
                $storedPath === ''
            ) {
                throw new RuntimeException(
                    'Unable to store the profile photo.'
                );
            }

            $newPhotoPath =
                $storedPath;

            $user->profile_photo_path =
                $newPhotoPath;

            $user->save();
        } catch (Throwable $exception) {
            if (
                is_string(
                    $newPhotoPath
                )
                &&
                $newPhotoPath !== ''
            ) {
                Storage::disk(
                    'public'
                )->delete(
                    $newPhotoPath
                );
            }

            throw $exception;
        }

        /*
        |--------------------------------------------------------------------------
        | Delete Replaced Photo
        |--------------------------------------------------------------------------
        */

        if (
            is_string(
                $oldPhotoPath
            )
            &&
            $oldPhotoPath !== ''
            &&
            $oldPhotoPath !==
                $newPhotoPath
        ) {
            Storage::disk(
                'public'
            )->delete(
                $oldPhotoPath
            );
        }

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Profile photo updated successfully.',

            'data' => [
                'user' =>
                    (
                        new UserResource(
                            $user->fresh()
                        )
                    )->resolve(
                        $request
                    ),
            ],
        ]);
    }


    /**
     * Remove the authenticated Shop Owner's profile photo.
     */
    public function destroy(
        Request $request
    ): JsonResponse {
        $user =
            $request->user();

        if (
            $user === null
            ||
            ! $user->isShopOwner()
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'You are not authorized to perform this action.',
            ], Response::HTTP_FORBIDDEN);
        }

        $oldPhotoPath =
            $user->profile_photo_path;

        /*
        |--------------------------------------------------------------------------
        | Remove Database Reference
        |--------------------------------------------------------------------------
        */

        $user->profile_photo_path =
            null;

        $user->save();

        /*
        |--------------------------------------------------------------------------
        | Remove File
        |--------------------------------------------------------------------------
        */

        if (
            is_string(
                $oldPhotoPath
            )
            &&
            $oldPhotoPath !== ''
        ) {
            Storage::disk(
                'public'
            )->delete(
                $oldPhotoPath
            );
        }

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Profile photo removed successfully.',

            'data' => [
                'user' =>
                    (
                        new UserResource(
                            $user->fresh()
                        )
                    )->resolve(
                        $request
                    ),
            ],
        ]);
    }
}