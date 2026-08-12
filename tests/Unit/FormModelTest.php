<?php

namespace Modules\Glitter\FormBuilder\Tests\Unit;

use Composer\Autoload\ClassLoader;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Modules\Glitter\FormBuilder\Enums\FormStatus;
use Modules\Glitter\FormBuilder\Models\Form;
use Modules\Glitter\FormBuilder\Models\FormVersion;
use PHPUnit\Framework\TestCase;
use LogicException;

final class FormModelTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $loader->addPsr4('Modules\\Glitter\\FormBuilder\\', dirname(__DIR__, 2).'/src');
        }
    }

    public function test_form_uses_numeric_primary_key_and_public_uuid_route_key(): void
    {
        $form = new Form;

        self::assertSame('id', $form->getKeyName());
        self::assertSame(['uuid'], $form->uniqueIds());
        self::assertSame('uuid', $form->getRouteKeyName());
        self::assertSame(FormStatus::class, $form->getCasts()['status']);
    }

    public function test_form_version_exposes_snapshot_as_array_and_rejects_mutation(): void
    {
        Model::setEventDispatcher(new Dispatcher(new Container));
        $version = new FormVersion;

        self::assertSame('array', $version->getCasts()['snapshot']);
        self::assertFalse($version->usesTimestamps());
        self::assertTrue($version->isImmutable());

        $this->expectException(LogicException::class);
        $method = new \ReflectionMethod($version, 'fireModelEvent');
        $method->invoke($version, 'updating');
    }
}
