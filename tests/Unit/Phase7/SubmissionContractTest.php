<?php

namespace Modules\Glitter\FormBuilder\Tests\Unit\Phase7;

use Modules\Glitter\FormBuilder\DTO\SubmissionContext;
use Modules\Glitter\FormBuilder\DTO\SubmissionInput;
use Modules\Glitter\FormBuilder\Events\SubmissionReceived;
use Modules\Glitter\FormBuilder\Services\CanonicalPayloadService;
use Modules\Glitter\FormBuilder\Services\SubmissionNormalizationService;
use Modules\Glitter\FormBuilder\Fields\BuiltIn\BuiltInFieldProviders;
use Modules\Glitter\FormBuilder\Fields\FieldTypeRegistry;
use Modules\Glitter\FormBuilder\Exceptions\SubmissionValidationException;
use PHPUnit\Framework\TestCase;

final class SubmissionContractTest extends TestCase
{
    public function test_canonical_hash_is_stable_across_object_key_order(): void
    {
        $service = new CanonicalPayloadService;

        self::assertSame(
            $service->hash(['b' => 2, 'a' => ['y' => 2, 'x' => 1]]),
            $service->hash(['a' => ['x' => 1, 'y' => 2], 'b' => 2]),
        );
    }

    public function test_submission_input_and_context_are_readonly_data_only_values(): void
    {
        $context = new SubmissionContext('guest', null, 'ko', 'Asia/Seoul', 'public', 'ip-hash', 'ua-hash');
        $input = new SubmissionInput(['email' => 'user@example.com'], $context, 'abcdefghijklmnop');

        self::assertTrue((new \ReflectionClass($context))->isReadOnly());
        self::assertTrue((new \ReflectionClass($input))->isReadOnly());
        self::assertSame('guest', $input->context->actorType);
    }

    public function test_received_event_contains_no_field_values_or_request_fingerprints(): void
    {
        $event = new SubmissionReceived('submission-uuid', 'form-uuid', 'contact', 1, 'guest', null, '2026-08-01T00:00:00Z', 'ko', 'received');
        $payload = $event->payload();

        self::assertSame(['submission_uuid', 'form_uuid', 'form_slug', 'revision', 'actor_type', 'submitted_user_uuid', 'received_at', 'locale', 'status'], array_keys($payload));
        self::assertStringNotContainsString('email', json_encode($payload, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('ip', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function test_normalization_ignores_hidden_override_records_server_consent_and_suppresses_sensitive_index(): void
    {
        $registry = new FieldTypeRegistry;
        BuiltInFieldProviders::register($registry);
        $service = new SubmissionNormalizationService($registry);
        $snapshot = $this->snapshot();
        $context = new SubmissionContext('guest', null, 'ko', 'Asia/Seoul', 'public', null, null);

        $result = $service->normalize($snapshot, [
            'email' => ' User@example.com ', 'token' => 'attacker', 'consent' => true,
        ], $context);

        self::assertSame('User@example.com', $result->payload['email']);
        self::assertSame('server-value', $result->payload['token']);
        self::assertTrue($result->payload['consent']['accepted']);
        self::assertSame('privacy-v1', $result->payload['consent']['statementId']);
        self::assertMatchesRegularExpression('/Z$/', $result->payload['consent']['acceptedAt']);
        self::assertSame([], $result->indexValues);
    }

    public function test_unknown_field_and_false_consent_fail_closed_without_raw_value_in_error(): void
    {
        $registry = new FieldTypeRegistry;
        BuiltInFieldProviders::register($registry);
        $service = new SubmissionNormalizationService($registry);
        $context = new SubmissionContext('guest', null, 'ko', 'UTC', 'public', null, null);

        try {
            $service->normalize($this->snapshot(), ['email' => 'user@example.com', 'token' => 'ignored', 'consent' => false, 'password' => 'secret-value'], $context);
            self::fail('Validation exception expected.');
        } catch (SubmissionValidationException $exception) {
            self::assertArrayHasKey('fields.password', $exception->errors());
            self::assertStringNotContainsString('secret-value', json_encode($exception->errors(), JSON_THROW_ON_ERROR));
        }

        try {
            $service->normalize($this->snapshot(), ['email' => 'user@example.com', 'token' => 'ignored', 'consent' => false], $context);
            self::fail('Consent validation exception expected.');
        } catch (SubmissionValidationException $exception) {
            self::assertArrayHasKey('fields.consent', $exception->errors());
        }
    }

    public function test_provider_storage_kinds_are_mapped_to_persistence_value_kinds(): void
    {
        $registry = new FieldTypeRegistry;
        BuiltInFieldProviders::register($registry);
        $snapshot = $this->snapshot();
        $snapshot['fields'][0]['sensitive'] = false;
        $snapshot['fields'][0]['indexable'] = true;

        $result = (new SubmissionNormalizationService($registry))->normalize($snapshot, [
            'email' => 'user@example.com', 'token' => 'ignored', 'consent' => true,
        ], new SubmissionContext('guest', null, 'ko', 'UTC', 'public', null, null));

        self::assertSame('text', $result->indexValues[0]['value_kind']);
        self::assertSame('user@example.com', $result->indexValues[0]['value_text']);
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        $base = static fn (string $key, string $type): array => [
            'key' => $key, 'type' => $type, 'provider' => 'glitter-form_builder', 'contractVersion' => '1.0',
            'label' => ['ko' => $key], 'description' => [], 'required' => true, 'default' => null,
            'options' => [], 'validation' => [], 'config' => [], 'sensitive' => false, 'indexable' => false, 'exportable' => true,
        ];
        $email = $base('email', 'email'); $email['sensitive'] = true;
        $hidden = $base('token', 'hidden'); $hidden['default'] = 'server-value'; $hidden['config'] = ['source' => 'schema_default']; $hidden['sensitive'] = true;
        $consent = $base('consent', 'consent'); $consent['default'] = false; $consent['validation'] = ['accepted' => true]; $consent['config'] = ['statementId' => 'privacy-v1'];

        return [
            'schemaVersion' => '1.0',
            'form' => ['publicId' => '01900000-0000-7000-8000-000000000001', 'slug' => 'contact', 'revision' => 1, 'accessMode' => 'guest_and_authenticated'],
            'fields' => [$email, $hidden, $consent],
            'publishedAt' => '2026-08-01T00:00:00Z',
        ];
    }
}
