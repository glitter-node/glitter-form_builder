<?php

namespace Modules\Glitter\FormBuilder\Tests\Unit;

use Illuminate\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Modules\Glitter\FormBuilder\Fields\FieldTypeRegistry;
use Modules\Glitter\FormBuilder\Module;
use Modules\Glitter\FormBuilder\Providers\FormBuilderServiceProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/module.php';

final class ServiceProviderFieldRegistryTest extends TestCase
{
    public function test_registry_is_singleton_and_sealed_after_application_boot(): void
    {
        $app = new Application(dirname(__DIR__, 5));
        $app->instance('translator', new Translator(new ArrayLoader, 'en'));
        $app->register(FormBuilderServiceProvider::class);

        $first = $app->make(FieldTypeRegistry::class);
        self::assertSame($first, $app->make(FieldTypeRegistry::class));
        self::assertFalse($first->isSealed());

        $app->boot();

        self::assertCount(11, $first->all());
        self::assertTrue($first->isSealed());
    }

    public function test_admin_menu_uses_localized_entity_names_for_each_locale(): void
    {
        $app = new Application(dirname(__DIR__, 5));
        $app->instance('translator', new Translator(new FileLoader(new Filesystem, $app->langPath()), 'en'));
        $app->register(FormBuilderServiceProvider::class);
        $app->boot();

        $menus = (new Module)->getAdminMenus();
        $permissions = (new Module)->getPermissions();

        self::assertSame('반짝이 폼 관리', $menus[0]['name']['ko']);
        self::assertSame('Glitter Form Management', $menus[0]['name']['en']);
        self::assertSame('폼', $menus[0]['children'][0]['name']['ko']);
        self::assertSame('Forms', $menus[0]['children'][0]['name']['en']);
        self::assertSame('glitter-form_builder', $menus[0]['slug']);
        self::assertSame('glitter-form_builder.forms.read', $menus[0]['children'][0]['permission']);
        self::assertSame('폼 관리', $permissions['name']['ko']);
        self::assertSame('Glitter Form Builder', $permissions['name']['en']);
    }
}
