<?php

namespace App\Http\Requests\Api\V1\ShopOwner;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

#[FailOnUnknownFields]
class StoreMediaImageRequest extends FormRequest
{
    /**
     * Authentication, role, phone verification and approved-shop
     * authorization are handled by route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validate actual uploaded image data.
     *
     * SVG is deliberately excluded.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'file',
                'image',

                /*
                 * Content/type allow-list.
                 */

                'mimes:jpg,jpeg,png,webp',

                /*
                 * Maximum upload size.
                 */

                'max:'
                .(int) config(
                    'shop-media.validation.max_kilobytes',
                    5120
                ),

                /*
                 * Prevent excessively large image dimensions.
                 */

                'dimensions:max_width='
                .(int) config(
                    'shop-media.validation.max_width',
                    4096
                )
                .',max_height='
                .(int) config(
                    'shop-media.validation.max_height',
                    4096
                ),
            ],
        ];
    }

    /**
     * Explicitly reject extra multipart fields.
     *
     * This prevents the client from attempting to submit fields such as:
     *
     * - shop_id
     * - image_path
     * - position
     */
    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(
            function (
                Validator $validator
            ): void {
                foreach (
                    array_keys(
                        $this->all()
                    ) as $field
                ) {
                    if (
                        $field ===
                        'image'
                    ) {
                        continue;
                    }

                    $validator
                        ->errors()
                        ->add(
                            $field,
                            "The {$field} field is not allowed."
                        );
                }
            }
        );
    }
}