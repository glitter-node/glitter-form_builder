<?php

namespace Modules\Glitter\FormBuilder\Services;

use InvalidArgumentException;
use Modules\Glitter\FormBuilder\Schema\FormSchemaParser;
use Modules\Glitter\FormBuilder\Schema\FormSchemaValidator;
use Modules\Glitter\FormBuilder\Schema\PublishSchemaValidator;
use Modules\Glitter\FormBuilder\Schema\ValueObjects\SchemaValidationResult;

final class SchemaEditingService
{
    public function __construct(
        private FormSchemaParser $parser,
        private FormSchemaValidator $structuralValidator,
        private PublishSchemaValidator $publishValidator,
    ) {}

    /** @param list<string> $supportedLocales */
    public function validate(array $schema, string $defaultLocale, array $supportedLocales, string $mode): SchemaValidationResult
    {
        $parsed = $this->parser->parse($schema);

        return match ($mode) {
            'draft' => $this->structuralValidator->validate($parsed, $defaultLocale, $supportedLocales, false),
            'publish-preview' => $this->publishValidator->validate($parsed, $defaultLocale, $supportedLocales),
            default => throw new InvalidArgumentException('schema_validation.unsupported_mode'),
        };
    }
}
