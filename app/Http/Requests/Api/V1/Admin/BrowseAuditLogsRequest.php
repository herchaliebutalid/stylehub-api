<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\AuditEvent;
use App\Enums\AuditResourceType;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

#[FailOnUnknownFields]
class BrowseAuditLogsRequest extends FormRequest
{
    /**
     * Query-string parameters explicitly supported by the
     * administrator audit-log endpoint.
     *
     * @var list<string>
     */
    private const ALLOWED_QUERY_FIELDS = [
        'event',
        'actor_id',
        'actor_role',
        'resource_type',
        'date_from',
        'date_to',
        'page',
        'per_page',
    ];

    public function authorize(): bool
    {
        /*
         * Authorization is handled by:
         *
         * auth:sanctum
         * account.active
         * role.admin
         */
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event' => [
                'sometimes',
                Rule::enum(
                    AuditEvent::class
                ),
            ],

            'actor_id' => [
                'sometimes',
                'integer',
                'min:1',
                'exists:users,id',
            ],

            'actor_role' => [
                'sometimes',
                Rule::enum(
                    UserRole::class
                ),
            ],

            'resource_type' => [
                'sometimes',
                Rule::enum(
                    AuditResourceType::class
                ),
            ],

            'date_from' => [
                'sometimes',
                'date_format:Y-m-d',
            ],

            'date_to' => [
                'sometimes',
                'date_format:Y-m-d',
                'after_or_equal:date_from',
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
     * GET query parameters need an explicit allow-list as an
     * additional defense against unsupported/probing fields.
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