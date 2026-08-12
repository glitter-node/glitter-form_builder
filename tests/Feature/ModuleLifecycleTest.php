<?php

namespace Modules\Glitter\FormBuilder\Tests\Feature;

use App\Models\Module as ModuleModel;
use Modules\Glitter\FormBuilder\Tests\ModuleTestCase;

final class ModuleLifecycleTest extends ModuleTestCase
{
    public function test_official_lifecycle_preserves_bundled_source_and_leaves_no_business_surface(): void
    {
        $this->assertIsolatedTestingDatabase();

        $identifier = 'glitter-form_builder';
        $activePath = base_path('modules/'.$identifier);
        self::assertDirectoryDoesNotExist($activePath);

        try {
            $this->artisan('module:install', ['identifier' => $identifier, '--vendor-mode' => 'composer'])
                ->assertSuccessful();
            self::assertDirectoryExists($activePath);
            self::assertSame('inactive', ModuleModel::query()->where('identifier', $identifier)->value('status'));

            $this->artisan('module:activate', ['identifier' => $identifier])->assertSuccessful();
            self::assertSame('active', ModuleModel::query()->where('identifier', $identifier)->value('status'));

            $this->artisan('module:deactivate', ['identifier' => $identifier])->assertSuccessful();
            self::assertSame('inactive', ModuleModel::query()->where('identifier', $identifier)->value('status'));

            self::assertFileDoesNotExist($activePath.'/routes/web.php');
            self::assertFileDoesNotExist($activePath.'/routes/api.php');
            self::assertDirectoryDoesNotExist($activePath.'/database/migrations');
            self::assertDirectoryExists(base_path('modules/_bundled/'.$identifier));
        } finally {
            if (ModuleModel::query()->where('identifier', $identifier)->exists()) {
                $this->artisan('module:uninstall', ['identifier' => $identifier, '--force' => true])
                    ->assertSuccessful();
            }
        }

        self::assertDirectoryDoesNotExist($activePath);
        self::assertDirectoryExists(base_path('modules/_bundled/'.$identifier));
    }
}
