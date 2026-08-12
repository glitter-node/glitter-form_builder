<?php

namespace Modules\Glitter\FormBuilder\Schema;

use Modules\Glitter\FormBuilder\Schema\ValueObjects\SchemaValidationResult;

final class SchemaValidationResponse
{
    public const HTTP_STATUS = 422;

    /** @param callable(string, array<string, bool|int|string>): string $translator */
    public function payload(SchemaValidationResult $result, callable $translator): array
    {
        return $result->toArray($translator);
    }
}
