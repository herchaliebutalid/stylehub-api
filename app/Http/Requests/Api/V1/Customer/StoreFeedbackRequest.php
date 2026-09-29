<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class StoreFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize customer-written feedback.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->exists('comment')) {
            return;
        }

        $comment = $this->input(
            'comment'
        );

        if (! is_string($comment)) {
            return;
        }

        $comment = trim(
            $comment
        );

        $this->merge([
            'comment' =>
                $comment === ''
                    ? null
                    : $comment,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rating' => [
                'required',
                'integer',
                'between:1,5',
            ],

            'comment' => [
                'sometimes',
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}