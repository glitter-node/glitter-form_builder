<?php

namespace Modules\Glitter\FormBuilder\Tests\Unit\Phase6;

use DomainException;
use Modules\Glitter\FormBuilder\Fields\BuiltIn\BuiltInFieldProviders;
use Modules\Glitter\FormBuilder\Fields\FieldTypeRegistry;
use Modules\Glitter\FormBuilder\Rendering\FormLayoutProjector;
use PHPUnit\Framework\TestCase;

final class FormLayoutProjectorTest extends TestCase
{
    public function test_projects_only_allowlisted_components_and_hides_server_defaults(): void
    {
        $projection = $this->projector()->project($this->snapshot([
            $this->field('email', 'email', ['default' => 'secret@example.com', 'sensitive' => true]),
            $this->field('department', 'radio', ['options' => [['value' => 'sales', 'label' => ['en' => 'Sales']]]]),
            $this->field('server_code', 'hidden', ['default' => 'internal']),
        ]))->toArray();

        self::assertSame('1.0', $projection['schemaVersion']);
        self::assertSame(['Input', 'Select'], array_column($projection['renderer']['fields'], 'component'));
        self::assertArrayNotHasKey('default', $projection['fields'][0]);
        self::assertArrayNotHasKey('default', $projection['fields'][2]);
        self::assertNotContains('Hidden', array_column($projection['renderer']['fields'], 'component'));
        self::assertSame('g7-layout', $projection['renderer']['kind']);
    }

    public function test_unknown_or_unavailable_renderer_fails_closed(): void
    {
        $this->expectException(DomainException::class);
        $this->projector()->project($this->snapshot([$this->field('phone', 'acme:phone')]));
    }

    public function test_null_description_projects_to_empty_locale_map_for_ko_and_en(): void
    {
        $snapshot = $this->snapshot([]);
        $snapshot['form']['title'] = ['ko' => '문의', 'en' => 'Contact'];
        $snapshot['form']['description'] = null;

        $projection = $this->projector()->project($snapshot)->toArray();

        self::assertSame([], $projection['form']['description']);
        self::assertSame('문의', $projection['form']['title']['ko']);
        self::assertSame('Contact', $projection['form']['title']['en']);
    }

    public function test_malformed_description_fails_closed(): void
    {
        $snapshot = $this->snapshot([]);
        $snapshot['form']['description'] = 'not-a-locale-map';

        $this->expectException(DomainException::class);
        $this->projector()->project($snapshot);
    }

    public function test_public_consent_exposes_only_the_statement_identifier(): void
    {
        $projection = $this->projector()->project($this->snapshot([
            $this->field('consent_1', 'consent', [
                'required' => true,
                'default' => false,
                'config' => ['statementId' => 'consent', 'policyVersion' => 'v1'],
            ]),
        ]))->toArray();

        self::assertSame(['statementId' => 'consent'], $projection['fields'][0]['config']);
        self::assertArrayNotHasKey('default', $projection['fields'][0]);
        self::assertArrayNotHasKey('policyVersion', $projection['fields'][0]['config']);
    }

    /** @param list<array<string, mixed>> $fields @return array<string, mixed> */
    private function snapshot(array $fields): array
    {
        return [
            'schemaVersion' => '1.0',
            'form' => [
                'publicId' => '01989f2e-2f2c-7d53-8f13-112233445566',
                'slug' => 'contact-us',
                'revision' => 1,
                'accessMode' => 'guest_and_authenticated',
                'title' => ['en' => 'Contact'],
                'description' => ['en' => 'Contact us'],
                'retention' => ['mode' => 'manual', 'days' => null, 'automaticEnforcement' => false],
            ],
            'fields' => $fields,
            'publishedAt' => '2026-07-31T00:00:00Z',
        ];
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function field(string $key, string $type, array $overrides = []): array
    {
        return array_merge([
            'key' => $key,
            'type' => $type,
            'provider' => 'glitter-form_builder',
            'contractVersion' => '1.0',
            'label' => ['en' => ucfirst($key)],
            'description' => [],
            'required' => false,
            'default' => null,
            'options' => [],
            'validation' => [],
            'config' => [],
            'sensitive' => false,
            'indexable' => false,
            'exportable' => true,
        ], $overrides);
    }

    private function projector(): FormLayoutProjector
    {
        $registry = new FieldTypeRegistry;
        BuiltInFieldProviders::register($registry);
        $registry->seal();

        return new FormLayoutProjector($registry);
    }
}
