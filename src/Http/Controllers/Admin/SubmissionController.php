<?php

namespace Modules\Glitter\FormBuilder\Http\Controllers\Admin;

use App\Http\Controllers\Api\Base\AdminBaseController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Modules\Glitter\FormBuilder\Http\Requests\Admin\SubmissionListRequest;
use Modules\Glitter\FormBuilder\Http\Resources\AdminSubmissionCollection;
use Modules\Glitter\FormBuilder\Http\Resources\AdminSubmissionDetailResource;
use Modules\Glitter\FormBuilder\Services\AdminSubmissionService;

final class SubmissionController extends AdminBaseController
{
    public function __construct(private readonly AdminSubmissionService $service) { parent::__construct(); }

    public function index(SubmissionListRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return $this->success('glitter-form_builder::messages.submission.fetch_success', new AdminSubmissionCollection($this->service->paginate($validated, (int) ($validated['per_page'] ?? 20))));
    }

    public function show(string $submission): JsonResponse
    {
        try {
            return $this->successWithResource('glitter-form_builder::messages.submission.fetch_success', new AdminSubmissionDetailResource($this->service->find($submission)));
        } catch (ModelNotFoundException) {
            return $this->notFound('glitter-form_builder::messages.submission.not_found');
        }
    }
}
