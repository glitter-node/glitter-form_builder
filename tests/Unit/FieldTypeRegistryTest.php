<?php

namespace Modules\Glitter\FormBuilder\Tests\Unit;

use Modules\Glitter\FormBuilder\Exceptions\Fields\DuplicateFieldTypeException;
use Modules\Glitter\FormBuilder\Exceptions\Fields\IncompatibleFieldContractException;
use Modules\Glitter\FormBuilder\Exceptions\Fields\SealedFieldRegistryException;
use Modules\Glitter\FormBuilder\Exceptions\Fields\UnknownFieldTypeException;
use Modules\Glitter\FormBuilder\Fields\BuiltIn\TextFieldProvider;
use Modules\Glitter\FormBuilder\Fields\FieldTypeRegistry;
use PHPUnit\Framework\TestCase;

final class FieldTypeRegistryTest extends TestCase
{
    public function test_register_resolve_sort_and_seal_contract(): void
    {
        $registry = new FieldTypeRegistry;
        $provider = new TextFieldProvider;

        $registry->register($provider);

        self::assertTrue($registry->has('text'));
        self::assertSame($provider, $registry->resolve('text'));
        self::assertSame(['text'], array_keys($registry->all()));
        self::assertSame('text', $registry->metadata()['text']['type']);

        $this->expectException(DuplicateFieldTypeException::class);
        $registry->register($provider);
    }

    public function test_unknown_type_fails_closed(): void
    {
        $this->expectException(UnknownFieldTypeException::class);
        (new FieldTypeRegistry)->resolve('unknown');
    }

    public function test_incompatible_major_fails_fast(): void
    {
        $provider = new class extends TextFieldProvider {
            public function contractVersion(): string
            {
                return '2.0';
            }
        };

        $this->expectException(IncompatibleFieldContractException::class);
        (new FieldTypeRegistry)->register($provider);
    }

    public function test_registration_after_seal_fails(): void
    {
        $registry = new FieldTypeRegistry;
        $registry->seal();

        self::assertTrue($registry->isSealed());
        $this->expectException(SealedFieldRegistryException::class);
        $registry->register(new TextFieldProvider);
    }

    public function test_registry_has_no_unregister_surface(): void
    {
        self::assertFalse(method_exists(FieldTypeRegistry::class, 'unregister'));
    }
}
