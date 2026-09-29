<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateAdminSmsTemplateRequest;
use App\Http\Resources\Api\V1\AdminSmsTemplateResource;
use App\Services\Admin\AdminSystemSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSystemSettingsController extends Controller
{
    public function __construct(
        private readonly AdminSystemSettingsService $settings
    ) {}

    public function index(
        Request $request
    ): JsonResponse {
        return response()->json([
            'success' =>
                true,

            'data' => [
                'sms' => [
                    'configuration' =>
                        $this->settings
                            ->smsConfiguration(),

                    'templates' =>
                        AdminSmsTemplateResource::collection(
                            $this->settings
                                ->templates()
                        )->resolve(
                            $request
                        ),
                ],
            ],

            'generated_at' =>
                now()
                    ->toISOString(),
        ]);
    }

    public function updateSmsTemplate(
        UpdateAdminSmsTemplateRequest $request,
        string $smsTemplateType
    ): JsonResponse {
        $template =
            $this->settings
                ->updateTemplate(
                    admin:
                        $request->user(),

                    typeValue:
                        $smsTemplateType,

                    templateText:
                        $request->validated(
                            'template'
                        ),
                );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'SMS template updated successfully. Future notifications will use the new template.',

            'data' => [
                'template' => (
                    new AdminSmsTemplateResource(
                        $template
                    )
                )->resolve(
                    $request
                ),
            ],
        ]);
    }
}