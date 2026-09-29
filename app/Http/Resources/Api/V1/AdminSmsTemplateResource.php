<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\SmsNotificationType;
use App\Support\Sms\SmsTemplateCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SmsTemplate
 */
class AdminSmsTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        $type =
            SmsNotificationType::from(
                (string) $this->type
            );

        return [
            'id' =>
                $this->exists
                    ? $this->id
                    : null,

            'type' =>
                $type->value,

            'label' =>
                $type->label(),

            'description' =>
                SmsTemplateCatalog::description(
                    $type
                ),

            'template' =>
                (string) $this->template,

            'allowed_placeholders' =>
                SmsTemplateCatalog::allowedPlaceholders(
                    $type
                ),

            'max_characters' =>
                SmsTemplateCatalog::MAX_TEMPLATE_CHARACTERS,

            'character_count' =>
                mb_strlen(
                    (string) $this->template
                ),

            'updated_by_admin_id' =>
                $this->updated_by,

            'updated_at' =>
                $this->updated_at
                    ?->toISOString(),
        ];
    }
}