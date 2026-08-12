<?php

namespace Modules\Glitter\FormBuilder\Http\Controllers\User;

use App\Enums\PermissionType;
use App\Http\Controllers\Api\Base\PublicBaseController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Modules\Glitter\FormBuilder\DTO\SubmissionInput;
use Modules\Glitter\FormBuilder\Exceptions\IdempotencyConflictException;
use Modules\Glitter\FormBuilder\Exceptions\SubmissionValidationException;
use Modules\Glitter\FormBuilder\Http\Requests\User\SubmitFormRequest;
use Modules\Glitter\FormBuilder\Http\Resources\SubmissionReceiptResource;
use Modules\Glitter\FormBuilder\Services\SubmissionContextFactory;
use Modules\Glitter\FormBuilder\Services\SubmissionService;

final class PublicSubmissionController extends PublicBaseController
{
    public function __construct(
        private readonly SubmissionService $service,
        private readonly SubmissionContextFactory $contexts,
    ) { parent::__construct(); }

    public function store(SubmitFormRequest $request, string $slug): JsonResponse
    {
        $user = $request->user();
        $admin = $user?->hasPermission('glitter-form_builder.forms.read', PermissionType::Admin) ?? false;
        $input = new SubmissionInput(
            (array) $request->validated('fields'),
            $this->contexts->fromRequest($request, $admin),
            $request->idempotencyKey(),
        );

        try {
            $receipt = $this->service->submit($slug, $input);

            return $this->successWithResource(
                'glitter-form_builder::messages.submission.received',
                new SubmissionReceiptResource($receipt),
                $receipt->idempotentReplay ? 200 : 201,
            );
        } catch (ModelNotFoundException) {
            return $this->notFound('glitter-form_builder::messages.form.not_found');
        } catch (SubmissionValidationException $exception) {
            return $this->validationError($exception->errors(), 'glitter-form_builder::messages.submission.validation_failed');
        } catch (IdempotencyConflictException) {
            return $this->error('glitter-form_builder::messages.submission.idempotency_conflict', 409);
        } catch (\Throwable) {
            return $this->error('glitter-form_builder::messages.submission.unavailable', 503);
        }
    }
}
