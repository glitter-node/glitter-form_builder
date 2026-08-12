<?php

namespace Modules\Glitter\FormBuilder\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class MigrationSyntaxTest extends TestCase
{
    public function test_phase_two_migrations_declare_portable_json_and_required_keys(): void
    {
        $path = dirname(__DIR__, 2).'/database/migrations';
        $files = glob($path.'/*.php') ?: [];

        self::assertCount(4, $files);
        $source = implode("\n", array_map(static fn (string $file): string => (string) file_get_contents($file), $files));

        self::assertStringContainsString("Schema::create('form_forms'", $source);
        self::assertStringContainsString("Schema::create('form_versions'", $source);
        self::assertStringContainsString("Schema::create('form_submissions'", $source);
        self::assertStringContainsString("Schema::create('form_submission_values'", $source);
        self::assertStringContainsString("\$table->json('draft_schema')", $source);
        self::assertStringContainsString("\$table->json('snapshot')", $source);
        self::assertStringContainsString("\$table->unique(['form_id', 'revision']", $source);
        self::assertStringNotContainsString('generatedAs', $source);
        self::assertStringNotContainsString('fullText', $source);
    }
}
