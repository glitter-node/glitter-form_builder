<?php

namespace Modules\Glitter\FormBuilder\Tests\Unit\Schema;

use Modules\Glitter\FormBuilder\Schema\Enums\SchemaIssueSeverity;
use Modules\Glitter\FormBuilder\Schema\SchemaValidationResponse;
use Modules\Glitter\FormBuilder\Schema\ValueObjects\SchemaValidationIssue;
use Modules\Glitter\FormBuilder\Schema\ValueObjects\SchemaValidationResult;
use PHPUnit\Framework\TestCase;

final class AdminPublishValidationResponseTest extends TestCase
{
    public function test_422_payload_is_stable_translated_and_redacted(): void
    {
        $result = new SchemaValidationResult([
            new SchemaValidationIssue('field.provider_unresolved', 'fields.2.type', 'glitter-form_builder::schema.errors.field_provider_unresolved', [], SchemaIssueSeverity::Error),
        ]);
        $formatter = new SchemaValidationResponse;
        $payload = $formatter->payload($result, static fn (string $key): string => 'translated:'.$key);

        self::assertSame(422, SchemaValidationResponse::HTTP_STATUS);
        self::assertSame('field.provider_unresolved', $payload['errors'][0]['code']);
        self::assertSame('fields.2.type', $payload['errors'][0]['path']);
        self::assertArrayHasKey('message_key', $payload['errors'][0]);
        self::assertArrayHasKey('message', $payload['errors'][0]);
        self::assertStringNotContainsString('default', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
