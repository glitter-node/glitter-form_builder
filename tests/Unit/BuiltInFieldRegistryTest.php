<?php

namespace Modules\Glitter\FormBuilder\Tests\Unit;

use Modules\Glitter\FormBuilder\Fields\BuiltIn\BuiltInFieldProviders;
use Modules\Glitter\FormBuilder\Fields\FieldTypeRegistry;
use PHPUnit\Framework\TestCase;

final class BuiltInFieldRegistryTest extends TestCase
{
    public function test_exact_built_in_set_is_registered(): void
    {
        $registry = new FieldTypeRegistry;
        BuiltInFieldProviders::register($registry);

        self::assertSame(
            ['checkbox', 'consent', 'date', 'datetime', 'email', 'hidden', 'number', 'radio', 'select', 'text', 'textarea'],
            array_keys($registry->all())
        );

        foreach ($registry->all() as $type => $provider) {
            self::assertMatchesRegularExpression('/^[a-z][a-z0-9_]{0,63}$/', $type);
            self::assertSame('glitter-form_builder', $provider->providerId());
            self::assertSame('1.0', $provider->contractVersion());
            self::assertSame($type, $provider->metadata()->type);
            json_encode($provider->configurationSchema(), JSON_THROW_ON_ERROR);
            json_encode($provider->rendererDescriptor()->toArray(), JSON_THROW_ON_ERROR);
        }
    }
}
