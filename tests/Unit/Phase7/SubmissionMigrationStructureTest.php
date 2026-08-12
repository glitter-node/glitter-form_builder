<?php

namespace Modules\Glitter\FormBuilder\Tests\Unit\Phase7;

use PHPUnit\Framework\TestCase;

final class SubmissionMigrationStructureTest extends TestCase
{
    public function test_submission_migrations_define_snapshot_idempotency_and_typed_projection_boundaries(): void
    {
        $root = dirname(__DIR__, 3);
        $submission = (string) file_get_contents($root.'/database/migrations/2026_08_01_000003_create_form_submissions_table.php');
        $values = (string) file_get_contents($root.'/database/migrations/2026_08_01_000004_create_form_submission_values_table.php');

        foreach (['normalized_payload', 'schema_snapshot_hash', 'payload_hash', 'idempotency_key', 'idempotency_scope', 'ip_hash', 'user_agent_hash', 'retention_due_at'] as $column) {
            self::assertStringContainsString("'{$column}'", $submission);
        }
        self::assertStringContainsString('form_submissions_idempotency_unique', $submission);
        foreach (['value_text', 'value_number', 'value_boolean', 'value_date', 'value_datetime', 'is_sensitive', 'is_indexable'] as $column) {
            self::assertStringContainsString("'{$column}'", $values);
        }
    }
}
