<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\SmsNotificationType;
use App\Support\Sms\SmsTemplateCatalog;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

#[FailOnUnknownFields]
class UpdateAdminSmsTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $template =
            $this->input(
                'template'
            );

        if (! is_string($template)) {
            return;
        }

        $this->merge([
            'template' =>
                trim(
                    str_replace(
                        ["\r\n", "\r"],
                        "\n",
                        $template
                    )
                ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'template' => [
                'required',
                'string',
                'min:1',
                'max:'
                    .SmsTemplateCatalog::MAX_TEMPLATE_CHARACTERS,
            ],
        ];
    }

    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(
            function (
                Validator $validator
            ): void {
                if (
                    $validator
                        ->errors()
                        ->has(
                            'template'
                        )
                ) {
                    return;
                }

                $type =
                    SmsNotificationType::tryFrom(
                        (string) $this->route(
                            'smsTemplateType'
                        )
                    );

                if (
                    $type === null
                    || ! SmsTemplateCatalog::isEditable(
                        $type
                    )
                ) {
                    return;
                }

                $template =
                    $this->input(
                        'template'
                    );

                if (! is_string($template)) {
                    return;
                }

                $error =
                    SmsTemplateCatalog::validationError(
                        $type,
                        $template
                    );

                if ($error !== null) {
                    $validator
                        ->errors()
                        ->add(
                            'template',
                            $error
                        );
                }
            }
        );
    }
}