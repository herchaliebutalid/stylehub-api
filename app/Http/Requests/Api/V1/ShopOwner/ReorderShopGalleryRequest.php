<?php

namespace App\Http\Requests\Api\V1\ShopOwner;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

#[FailOnUnknownFields]
class ReorderShopGalleryRequest extends FormRequest
{
    /**
     * Route middleware handles Shop Owner authorization.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'image_ids' => [
                'required',
                'array',

                'max:'
                .(int) config(
                    'shop-media.gallery.max_images',
                    10
                ),
            ],

            'image_ids.*' => [
                'required',
                'integer',
                'distinct',
                'min:1',
            ],
        ];
    }

    /**
     * Reject unsupported request fields.
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
                        'image_ids'
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