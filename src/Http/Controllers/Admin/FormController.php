<?php

namespace Modules\Glitter\FormBuilder\Http\Controllers\Admin;

use App\Http\Controllers\Api\Base\AdminBaseController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Modules\Glitter\FormBuilder\Http\Requests\Admin\FormListRequest;
use Modules\Glitter\FormBuilder\Http\Requests\Admin\PublishFormRequest;
use Modules\Glitter\FormBuilder\Http\Requests\Admin\StoreFormRequest;
use Modules\Glitter\FormBuilder\Http\Requests\Admin\UpdateFormRequest;
use Modules\Glitter\FormBuilder\Http\Resources\FormCollection;
use Modules\Glitter\FormBuilder\Http\Resources\FormResource;
use Modules\Glitter\FormBuilder\Http\Resources\FormVersionResource;
use Modules\Glitter\FormBuilder\Models\Form;
use Modules\Glitter\FormBuilder\Exceptions\Schema\FormSchemaValidationException;
use Modules\Glitter\FormBuilder\Schema\SchemaValidationResponse;
use Modules\Glitter\FormBuilder\Services\FormService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class FormController extends AdminBaseController
{
    public function __construct(private FormService $service)
    {
        parent::__construct();
    }

    public function index(FormListRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $forms = $this->service->paginate($validated, (int) ($validated['per_page'] ?? 20));

        return $this->success('glitter-form_builder::messages.form.fetch_success', new FormCollection($forms));
    }

    public function store(StoreFormRequest $request): JsonResponse
    {
        $form = $this->service->create($request->validated());

        return $this->successWithResource('glitter-form_builder::messages.form.create_success', new FormResource($form), 201);
    }

    public function show(Form $form): JsonResponse
    {
        try {
            return $this->successWithResource('glitter-form_builder::messages.form.fetch_success', new FormResource($this->service->find($form->uuid)));
        } catch (ModelNotFoundException) {
            return $this->notFound('glitter-form_builder::messages.form.not_found');
        } catch (AccessDeniedHttpException) {
            return $this->error('auth.scope_denied', 403);
        }
    }

    public function update(UpdateFormRequest $request, Form $form): JsonResponse
    {
        try {
            return $this->successWithResource(
                'glitter-form_builder::messages.form.update_success',
                new FormResource($this->service->update($form, $request->validated()))
            );
        } catch (AccessDeniedHttpException) {
            return $this->error('auth.scope_denied', 403);
        } catch (\DomainException $exception) {
            return $this->error($exception->getMessage(), 409);
        }
    }

    public function destroy(Form $form): JsonResponse
    {
        try {
            $this->service->delete($form);

            return $this->success('glitter-form_builder::messages.form.delete_success');
        } catch (AccessDeniedHttpException) {
            return $this->error('auth.scope_denied', 403);
        }
    }

    public function publish(PublishFormRequest $request, Form $form): JsonResponse
    {
        try {
            return $this->successWithResource(
                'glitter-form_builder::messages.form.publish_success',
                new FormVersionResource($this->service->publish($form))
            );
        } catch (AccessDeniedHttpException) {
            return $this->error('auth.scope_denied', 403);
        } catch (FormSchemaValidationException $exception) {
            $formatter = new SchemaValidationResponse;

            return $this->validationError(
                $formatter->payload($exception->result(), static fn (string $key, array $parameters): string => __($key, $parameters)),
                'glitter-form_builder::schema.publish_validation_failed',
            );
        } catch (\DomainException $exception) {
            return $this->error($exception->getMessage(), 409);
        }
    }
}
