<?php

namespace Modules\Glitter\FormBuilder\Http\Controllers\User;

use App\Enums\PermissionType;
use App\Http\Controllers\Api\Base\PublicBaseController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Glitter\FormBuilder\Exceptions\Rendering\PublicFormProjectionException;
use Modules\Glitter\FormBuilder\Http\Resources\PublicFormSchemaResource;
use Modules\Glitter\FormBuilder\Services\PublicFormService;

final class PublicFormSchemaController extends PublicBaseController
{
    public function __construct(private readonly PublicFormService $service)
    {
        parent::__construct();
    }

    // audit:allow controller-base-request-injection reason: GET read path에서 optional auth actor만 확인하며 request body는 사용하지 않음
    public function show(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
        $admin = $user?->hasPermission('glitter-form_builder.forms.read', PermissionType::Admin) ?? false;

        try {
            $schema = $this->service->schema($slug, $user !== null, $admin);

            return $this->successWithResource(
                'glitter-form_builder::messages.public_schema.fetch_success',
                new PublicFormSchemaResource($schema),
            );
        } catch (ModelNotFoundException) {
            return $this->notFound('glitter-form_builder::messages.form.not_found');
        } catch (PublicFormProjectionException $exception) {
            Log::error('Glitter Form Builder public projection failed.', [
                'form_uuid' => $exception->formUuid,
                'slug' => $exception->slug,
                'version_id' => $exception->versionId,
                'version_uuid' => $exception->versionUuid,
                'error_category' => $exception->category,
            ]);

            return $this->error('glitter-form_builder::messages.public_schema.internal_error', 500);
        }
    }
}
