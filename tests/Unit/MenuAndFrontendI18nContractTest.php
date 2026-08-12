<?php

declare(strict_types=1);

namespace Modules\Glitter\FormBuilder\Tests\Unit;

use Modules\Glitter\FormBuilder\Module;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

require_once dirname(__DIR__, 2).'/module.php';

final class MenuAndFrontendI18nContractTest extends TestCase
{
    public function test_menu_and_permission_entities_are_fully_translated_before_sync(): void
    {
        $module = new Module;
        $parent = $module->getAdminMenus()[0];
        $forms = $parent['children'][0];
        $submissions = $parent['children'][1];
        $permissions = $module->getPermissions();

        self::assertSame(['ko' => '반짝이 폼 관리', 'en' => 'Glitter Form Management'], $parent['name']);
        self::assertSame(['ko' => '폼', 'en' => 'Forms'], $forms['name']);
        self::assertSame(['ko' => '제출', 'en' => 'Submissions'], $submissions['name']);
        self::assertSame(['ko' => '폼 관리', 'en' => 'Glitter Form Builder'], $permissions['name']);
        self::assertSame(['ko' => '제출', 'en' => 'Submissions'], $permissions['categories'][1]['name']);

        foreach ([$parent['name'], $forms['name'], $submissions['name'], $permissions['name'], $permissions['categories'][1]['name']] as $name) {
            foreach ($name as $label) {
                self::assertStringNotContainsString('::', $label);
                self::assertStringNotContainsString('$t:', $label);
            }
        }

        self::assertSame(
            ['glitter-form_builder', null, 'fas fa-list-alt', 35],
            [$parent['slug'], $parent['url'], $parent['icon'], $parent['order']],
        );
        self::assertSame(
            ['glitter-form_builder-forms', '/admin/form-builder/forms', 'glitter-form_builder.forms.read', 'fas fa-list', 1],
            [$forms['slug'], $forms['url'], $forms['permission'], $forms['icon'], $forms['order']],
        );
        self::assertSame(
            ['glitter-form_builder-submissions', '/admin/form-builder/submissions', 'glitter-form_builder.submissions.read', 'fas fa-inbox', 2],
            [$submissions['slug'], $submissions['url'], $submissions['permission'], $submissions['icon'], $submissions['order']],
        );
    }

    public function test_every_layout_and_route_translation_resolves_in_korean_and_english(): void
    {
        $root = dirname(__DIR__, 2);
        $catalogs = [];
        foreach (['ko', 'en'] as $locale) {
            $catalogs[$locale] = json_decode((string) file_get_contents("{$root}/resources/lang/{$locale}.json"), true, flags: JSON_THROW_ON_ERROR);
        }

        $keys = [];
        foreach (['layouts', 'routes'] as $directory) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/resources/{$directory}"));
            foreach ($files as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'json') {
                    continue;
                }
                preg_match_all('/\\$t:(glitter-form_builder\\.[A-Za-z0-9_.-]+)/', (string) file_get_contents($file->getPathname()), $matches);
                array_push($keys, ...$matches[1]);
            }
        }

        $keys = array_values(array_unique($keys));
        self::assertNotEmpty($keys);
        foreach ($keys as $key) {
            $path = explode('.', substr($key, strlen('glitter-form_builder.')));
            foreach ($catalogs as $locale => $catalog) {
                $resolved = $catalog;
                foreach ($path as $segment) {
                    self::assertIsArray($resolved, "{$locale}: {$key} 중간 경로가 객체가 아닙니다.");
                    self::assertArrayHasKey($segment, $resolved, "{$locale}: {$key} 번역이 없습니다.");
                    $resolved = $resolved[$segment];
                }
                self::assertIsString($resolved, "{$locale}: {$key} 번역 결과가 문자열이 아닙니다.");
                self::assertNotSame($key, $resolved, "{$locale}: {$key}가 자기 자신으로 fallback 되었습니다.");
            }
        }
    }

    public function test_admin_index_pages_share_the_responsive_admin_spacing_wrapper(): void
    {
        $root = dirname(__DIR__, 2).'/resources/layouts/admin';

        foreach (['admin_form_builder_form_index.json', 'admin_form_builder_submission_index.json'] as $file) {
            $layout = json_decode((string) file_get_contents("{$root}/{$file}"), true, flags: JSON_THROW_ON_ERROR);
            $wrapper = $layout['slots']['content'][0];

            self::assertSame('Div', $wrapper['name']);
            self::assertSame(
                'gfb-admin admin-page-content-responsive-fluid space-y-6',
                $wrapper['props']['className'],
            );
            self::assertSame('gfb-admin-header', $wrapper['children'][0]['props']['className']);
            self::assertSame('gfb-panel gfb-filter-bar', $wrapper['children'][1]['props']['className']);
            self::assertSame('gfb-panel', $wrapper['children'][2]['props']['className']);
        }
    }
}
