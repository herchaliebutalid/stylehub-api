<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProfilePhotoController extends Controller
{
    /**
     * Upload or replace the authenticated customer's profile photo.
     */
    public function store(
        Request $request
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Secure Image Validation
        |--------------------------------------------------------------------------
        |
        | Only normal image formats are accepted.
        |
        | SVG is deliberately excluded because SVG files can contain active
        | content and are unnecessary for customer profile photos.
        |
        | Maximum upload:
        |
        | 5 MB
        |
        | Maximum dimensions:
        |
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

        $user =
            $request->user();

        $photo =
            $request->file(
                'photo'
            );

        if ($photo === null) {
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
        | Store New Photo First
        |--------------------------------------------------------------------------
        |
        | We do not delete the customer's existing photo until the new image
        | has successfully been stored and the database has been updated.
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
                || $storedPath === ''
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
            /*
            |--------------------------------------------------------------------------
            | Clean Up Failed Upload
            |--------------------------------------------------------------------------
            */

            if (
                is_string(
                    $newPhotoPath
                )
                && $newPhotoPath !== ''
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
        | Remove Replaced Photo
        |--------------------------------------------------------------------------
        */

        if (
            is_string(
                $oldPhotoPath
            )
            && $oldPhotoPath !== ''
            && $oldPhotoPath !==
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
     * Remove the authenticated customer's profile photo.
     */
    public function destroy(
        Request $request
    ): JsonResponse {
        $user =
            $request->user();

        $oldPhotoPath =
            $user->profile_photo_path;

        /*
        |--------------------------------------------------------------------------
        | Remove Database Reference First
        |--------------------------------------------------------------------------
        */

        $user->profile_photo_path =
            null;

        $user->save();

        /*
        |--------------------------------------------------------------------------
        | Delete Physical File
        |--------------------------------------------------------------------------
        */

        if (
            is_string(
                $oldPhotoPath
            )
            && $oldPhotoPath !== ''
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