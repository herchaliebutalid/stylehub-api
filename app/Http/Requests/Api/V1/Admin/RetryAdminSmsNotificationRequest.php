<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RetryAdminSmsNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

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
                    $validator
                        ->errors()
                        ->add(
                            $field,
                            "The {$field} field is not allowed for an SMS retry."
                        );
                }
            }
        );
    }
}