<?php

namespace Modules\Glitter\FormBuilder\Tests\Unit\Schema;

use Modules\Glitter\FormBuilder\Schema\Enums\SchemaIssueSeverity;
use Modules\Glitter\FormBuilder\Schema\ValueObjects\SchemaValidationIssue;
use Modules\Glitter\FormBuilder\Schema\ValueObjects\SchemaValidationResult;
use PHPUnit\Framework\TestCase;

final class SchemaValidationResultTest extends TestCase
{
    public function test_immutable_result_separates_errors_and_warnings(): void
    {
        $error = new SchemaValidationIssue('schema.invalid', 'fields', 'glitter-form_builder::schema.errors.invalid', [], SchemaIssueSeverity::Error);
        $warning = new SchemaValidationIssue('field.renderer_unavailable', 'fields.0.type', 'glitter-form_builder::schema.warnings.renderer_unavailable', [], SchemaIssueSeverity::Warning);
        $result = new SchemaValidationResult([$error, $warning]);

        self::assertFalse($result->isValid());
        self::assertSame([$error], $result->errors());
        self::assertSame([$warning], $result->warnings());
        self::assertTrue((new \ReflectionClass($result))->isReadOnly());
        json_encode($result->toArray(), JSON_THROW_ON_ERROR);
    }
}
