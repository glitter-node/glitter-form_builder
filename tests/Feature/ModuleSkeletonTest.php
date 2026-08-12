<?php

namespace Modules\Glitter\FormBuilder\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class ModuleSkeletonTest extends TestCase
{
    private string $modulePath;

    protected function setUp(): void
    {
        $this->modulePath = dirname(__DIR__, 2);
    }

    public function test_module_package_structure_is_consistent(): void
    {
        foreach (['module.json', 'module.php', 'composer.json', 'LICENSE', 'CHANGELOG.md'] as $file) {
            self::assertFileExists($this->modulePath.'/'.$file);
        }

        $manifest = $this->json('module.json');
        $composer = $this->json('composer.json');
        $package = $this->json('package.json');
        $packageLock = $this->json('package-lock.json');

        self::assertSame('glitter-form_builder', $manifest['identifier']);
        self::assertSame('Glitter.kr', $manifest['vendor']);
        self::assertSame('반짝이 폼 관리', $manifest['name']['ko']);
        self::assertSame('Glitter Form Management', $manifest['name']['en']);
        self::assertSame('0.3.16', $manifest['version']);
        self::assertSame('MIT', $manifest['license']);
        self::assertSame(['modules' => [], 'plugins' => []], $manifest['dependencies']);
        self::assertArrayNotHasKey('routes', $manifest);
        self::assertArrayNotHasKey('permissions', $manifest);
        self::assertArrayNotHasKey('menus', $manifest);
        self::assertSame('resources/js/index.ts', $manifest['assets']['js']['entry']);
        self::assertSame('dist/js/module.iife.js', $manifest['assets']['js']['output']);
        self::assertSame('resources/css/main.css', $manifest['assets']['css']['entry']);
        self::assertSame('dist/css/module.css', $manifest['assets']['css']['output']);
        self::assertTrue($manifest['assets']['handlers']);

        self::assertSame('modules/glitter-form_builder', $composer['name']);
        self::assertSame('library', $composer['type']);
        self::assertSame('0.3.16', $composer['version']);
        self::assertSame('MIT', $composer['license']);
        self::assertSame('Glitter.kr', $composer['authors'][0]['name']);
        self::assertSame('src/', $composer['autoload']['psr-4']['Modules\\Glitter\\FormBuilder\\']);
        self::assertSame('tests/', $composer['autoload-dev']['psr-4']['Modules\\Glitter\\FormBuilder\\Tests\\']);
        self::assertSame('0.3.16', $package['version']);
        self::assertSame('0.3.16', $packageLock['version']);
        self::assertSame('0.3.16', $packageLock['packages']['']['version']);

        $moduleSource = file_get_contents($this->modulePath.'/module.php');
        $providerSource = file_get_contents($this->modulePath.'/src/Providers/FormBuilderServiceProvider.php');
        self::assertIsString($moduleSource);
        self::assertIsString($providerSource);
        self::assertStringContainsString('namespace Modules\\Glitter\\FormBuilder;', $moduleSource);
        self::assertStringContainsString('extends AbstractModule', $moduleSource);
        self::assertStringContainsString('namespace Modules\\Glitter\\FormBuilder\\Providers;', $providerSource);
        self::assertStringContainsString("protected string \$moduleIdentifier = 'glitter-form_builder';", $providerSource);

        $koModuleTranslations = require $this->modulePath.'/src/lang/ko/module.php';
        $enModuleTranslations = require $this->modulePath.'/src/lang/en/module.php';
        self::assertSame('반짝이 폼 관리', $koModuleTranslations['name']);
        self::assertSame('Glitter Form Management', $enModuleTranslations['name']);
        self::assertSame('반짝이 폼 관리', $koModuleTranslations['menu']['form_builder']);
        self::assertSame('Glitter Form Management', $enModuleTranslations['menu']['form_builder']);
        self::assertSame('폼', $koModuleTranslations['menu']['forms']);
        self::assertSame('Forms', $enModuleTranslations['menu']['forms']);
        self::assertStringNotContainsString("'glitter-form_builder::module.menu.form_builder'", $moduleSource);
        $adminMenusSource = substr(
            $moduleSource,
            strpos($moduleSource, 'public function getAdminMenus'),
            strpos($moduleSource, 'private function permission') - strpos($moduleSource, 'public function getAdminMenus')
        );
        self::assertStringContainsString("'ko' => '반짝이 폼 관리'", $adminMenusSource);

        $this->assertTranslationParity('src/lang/ko/module.php', 'src/lang/en/module.php');
        $this->assertJsonTranslationParity('resources/lang/ko.json', 'resources/lang/en.json');
        self::assertStringContainsString('0.3.3', (string) file_get_contents($this->modulePath.'/CHANGELOG.md'));
        self::assertDirectoryExists($this->modulePath.'/docs/design');
        self::assertDirectoryExists($this->modulePath.'/docs/contracts');

        foreach (['database/migrations', 'src/Models', 'src/Contracts', 'src/Services', 'src/Repositories', 'src/Http', 'src/Fields', 'src/Schema', 'src/Rendering', 'resources/layouts/admin', 'resources/layouts/user', 'resources/routes', 'resources/js'] as $required) {
            self::assertDirectoryExists($this->modulePath.'/'.$required);
        }

        foreach (['routes', 'src/Jobs'] as $forbidden) {
            self::assertDirectoryDoesNotExist($this->modulePath.'/'.$forbidden);
        }

        $legacyIdentifier = 'glitter-'.'formbuilder';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->modulePath));
        foreach ($files as $file) {
            if (! $file->isFile() || str_contains($file->getPathname(), '/node_modules/')) {
                continue;
            }
            self::assertStringNotContainsString($legacyIdentifier, (string) file_get_contents($file->getPathname()), $file->getPathname());
        }
    }

    /** @return array<string, mixed> */
    private function json(string $relativePath): array
    {
        $decoded = json_decode((string) file_get_contents($this->modulePath.'/'.$relativePath), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    private function assertTranslationParity(string $koPath, string $enPath): void
    {
        $ko = require $this->modulePath.'/'.$koPath;
        $en = require $this->modulePath.'/'.$enPath;
        self::assertSame(array_keys($ko), array_keys($en));
    }

    private function assertJsonTranslationParity(string $koPath, string $enPath): void
    {
        self::assertSame(array_keys($this->json($koPath)), array_keys($this->json($enPath)));
    }
}
