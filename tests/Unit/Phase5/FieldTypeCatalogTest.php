<?php

namespace Modules\Glitter\FormBuilder\Tests\Unit\Phase5;

use Modules\Glitter\FormBuilder\Fields\BuiltIn\BuiltInFieldProviders;
use Modules\Glitter\FormBuilder\Fields\FieldDefinitionFactory;
use Modules\Glitter\FormBuilder\Fields\FieldTypeRegistry;
use Modules\Glitter\FormBuilder\Schema\FormSchemaParser;
use Modules\Glitter\FormBuilder\Schema\FormSchemaValidator;
use Modules\Glitter\FormBuilder\Schema\PublishSchemaValidator;
use Modules\Glitter\FormBuilder\Services\FieldTypeCatalog;
use PHPUnit\Framework\TestCase;
use Illuminate\Contracts\Translation\Translator;

final class FieldTypeCatalogTest extends TestCase
{
    public function test_catalog_exposes_safe_canonical_templates(): void
    {
        $registry = new FieldTypeRegistry;
        BuiltInFieldProviders::register($registry);
        $items = (new FieldTypeCatalog($registry, new FieldDefinitionFactory, $this->translator()))->all('en');

        self::assertCount(11, $items);
        self::assertSame('1.0', $items[0]['contractVersion']);
        self::assertArrayHasKey('definitionTemplate', $items[0]);
        self::assertArrayHasKey('contractVersion', $items[0]['definitionTemplate']);
        self::assertStringNotContainsString('class', json_encode($items, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('callback', json_encode($items, JSON_THROW_ON_ERROR));
        self::assertSame(array_values(array_unique(array_column($items, 'type'))), array_column($items, 'type'));
        $sorted = array_column($items, 'type');
        $expected = $sorted;
        sort($expected, SORT_STRING);
        self::assertSame($expected, $sorted);
    }

    public function test_checkbox_template_is_single_boolean(): void
    {
        $registry = new FieldTypeRegistry;
        BuiltInFieldProviders::register($registry);
        $items = (new FieldTypeCatalog($registry, new FieldDefinitionFactory, $this->translator()))->all('en');
        $checkbox = array_values(array_filter($items, static fn (array $item): bool => $item['type'] === 'checkbox'))[0];

        self::assertSame([], $checkbox['definitionTemplate']['options']);
        self::assertFalse($checkbox['definitionTemplate']['required']);
        $consent = array_values(array_filter($items, static fn (array $item): bool => $item['type'] === 'consent'))[0];
        self::assertNotTrue($consent['definitionTemplate']['default']);
        $hidden = array_values(array_filter($items, static fn (array $item): bool => $item['type'] === 'hidden'))[0];
        self::assertNull($hidden['definitionTemplate']['default']);
        self::assertArrayNotHasKey('serverDefault', $hidden['definitionTemplate']);
        $number = array_values(array_filter($items, static fn (array $item): bool => $item['type'] === 'number'))[0];
        self::assertFalse(is_float($number['definitionTemplate']['default']));
    }

    public function test_default_consent_template_satisfies_the_provider_contract(): void
    {
        $registry = new FieldTypeRegistry;
        BuiltInFieldProviders::register($registry);
        $items = (new FieldTypeCatalog($registry, new FieldDefinitionFactory, $this->translator()))->all('en');
        $consent = array_values(array_filter($items, static fn (array $item): bool => $item['type'] === 'consent'))[0]['definitionTemplate'];
        $consent['key'] = 'consent_1';

        self::assertSame('consent', $consent['config']['statementId']);
        $registry->seal();
        $result = (new PublishSchemaValidator(new FormSchemaValidator, $registry))->validate(
            (new FormSchemaParser)->parse(['schemaVersion' => '1.0', 'fields' => [$consent]]),
            'en',
            ['en'],
        );

        self::assertTrue($result->isValid(), json_encode($result->toArray(), JSON_THROW_ON_ERROR));
    }

    private function translator(): Translator
    {
        $translator = $this->createMock(Translator::class);
        $translator->method('get')->willReturnCallback(static fn (string $key): string => $key);
        return $translator;
    }
}
