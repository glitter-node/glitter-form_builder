<?php

namespace Modules\Glitter\FormBuilder\Http\Controllers\Admin;

use App\Http\Controllers\Api\Base\AdminBaseController;
use Illuminate\Http\JsonResponse;
use Modules\Glitter\FormBuilder\Http\Requests\Admin\ValidateSchemaRequest;
use Modules\Glitter\FormBuilder\Schema\SchemaValidationResponse;
use Modules\Glitter\FormBuilder\Services\SchemaEditingService;

final class SchemaValidationController extends AdminBaseController
{
    public function __construct(private SchemaEditingService $service) { parent::__construct(); }

    public function validateSchema(ValidateSchemaRequest $request): JsonResponse
    {
        $input = $request->validated();
        $result = $this->service->validate(
            $input['schema'],
            $input['defaultLocale'],
            array_values($input['supportedLocales']),
            $input['mode'],
        );
        $payload = (new SchemaValidationResponse)->payload(
            $result,
            static fn (string $key, array $parameters): string => __($key, $parameters),
        );

        return $this->success('glitter-form_builder::messages.schema.validation_complete', [
            'valid' => ! $result->hasErrors(),
            ...$payload,
        ]);
    }
}
