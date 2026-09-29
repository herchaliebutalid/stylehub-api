<?php

namespace App\Http\Requests\Api\V1\Notifications;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

#[FailOnUnknownFields]
class BrowseInAppNotificationsRequest extends NotificationAccessRequest
{
    /**
     * Query parameters explicitly supported by notification browsing.
     *
     * @var list<string>
     */
    private const ALLOWED_QUERY_FIELDS = [
        'status',
        'page',
        'per_page',
    ];

    /**
     * Normalize supported query parameters.
     */
    protected function prepareForValidation(): void
    {
        $normalized =
            [];

        foreach (
            self::ALLOWED_QUERY_FIELDS as $field
        ) {
            if (! $this->exists($field)) {
                continue;
            }

            $value =
                $this->input(
                    $field
                );

            $normalized[$field] =
                is_string($value)
                    ? trim($value)
                    : $value;
        }

        if ($normalized !== []) {
            $this->merge(
                $normalized
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'sometimes',

                Rule::in([
                    'all',
                    'unread',
                    'read',
                ]),
            ],

            'page' => [
                'sometimes',
                'integer',
                'min:1',
            ],

            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:50',
            ],
        ];
    }

    /**
     * Reject unsupported query-string parameters explicitly.
     *
     * FailOnUnknownFields is retained as defense in depth, but Laravel's
     * unknown-field handling does not reliably cover arbitrary query-string
     * keys for this request. This check makes the API contract explicit.
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
                        $this->query()
                    ) as $field
                ) {
                    if (
                        in_array(
                            $field,
                            self::ALLOWED_QUERY_FIELDS,
                            true
                        )
                    ) {
                        continue;
                    }

                    $validator
                        ->errors()
                        ->add(
                            $field,
                            "The {$field} query parameter is not allowed."
                        );
                }
            }
        );
    }
}