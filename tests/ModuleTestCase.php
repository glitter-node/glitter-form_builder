<?php

namespace Modules\Glitter\FormBuilder\Tests;

use App\Extension\ModuleManager;
use Composer\Autoload\ClassLoader;
use Illuminate\Support\Facades\DB;
use Modules\Glitter\FormBuilder\Module;
use Modules\Glitter\FormBuilder\Providers\FormBuilderServiceProvider;
use PDO;
use Tests\TestCase;

abstract class ModuleTestCase extends TestCase
{
    protected function assertIsolatedTestingDatabase(): void
    {
        self::assertSame(
            'testing',
            app()->environment(),
            'DB write test는 APP_ENV=testing 환경에서만 실행할 수 있습니다.'
        );

        $connection = DB::connection();

        $writeDatabase = $this->resolveDatabaseName(
            $connection->getPdo()
        );

        $readDatabase = $this->resolveDatabaseName(
            $connection->getReadPdo()
        );

        if ($writeDatabase === null) {
            self::markTestSkipped(
                '실제 write PDO의 DB 이름을 확인할 수 없어 lifecycle write test를 건너뜁니다.'
            );
        }

        if ($readDatabase === null) {
            self::markTestSkipped(
                '실제 read PDO의 DB 이름을 확인할 수 없어 lifecycle write test를 건너뜁니다.'
            );
        }

        if (! $this->isTestingDatabaseName($writeDatabase)) {
            self::markTestSkipped(
                sprintf(
                    'write DB [%s]가 명시적인 testing 전용 DB가 아니므로 lifecycle write test를 건너뜁니다.',
                    $writeDatabase
                )
            );
        }

        if (! $this->isTestingDatabaseName($readDatabase)) {
            self::markTestSkipped(
                sprintf(
                    'read DB [%s]가 명시적인 testing 전용 DB가 아니므로 lifecycle write test를 건너뜁니다.',
                    $readDatabase
                )
            );
        }

        if ($readDatabase !== $writeDatabase) {
            self::markTestSkipped(
                sprintf(
                    'read DB [%s]와 write DB [%s]가 달라 lifecycle write test를 건너뜁니다.',
                    $readDatabase,
                    $writeDatabase
                )
            );
        }
    }

    private function resolveDatabaseName(PDO $pdo): ?string
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $database = match ($driver) {
            'mysql' => $pdo->query('SELECT DATABASE()')?->fetchColumn(),
            'pgsql' => $pdo->query('SELECT current_database()')?->fetchColumn(),
            'sqlite' => $this->resolveSqliteDatabaseName($pdo),
            default => null,
        };

        if (! is_string($database)) {
            return null;
        }

        $database = trim($database);

        return $database !== '' ? $database : null;
    }

    private function resolveSqliteDatabaseName(PDO $pdo): ?string
    {
        $statement = $pdo->query('PRAGMA database_list');

        if ($statement === false) {
            return null;
        }

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            if (($row['name'] ?? null) !== 'main') {
                continue;
            }

            $filename = $row['file'] ?? null;

            if (! is_string($filename) || trim($filename) === '') {
                return 'testing_sqlite_memory';
            }

            return $filename;
        }

        return null;
    }

    private function isTestingDatabaseName(string $database): bool
    {
        return str_contains(strtolower($database), 'test');
    }

    protected function bootPhaseTwoDatabaseModule(): void
    {
        $this->assertIsolatedTestingDatabase();

        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $loader->addPsr4(
                'Modules\\Glitter\\FormBuilder\\',
                dirname(__DIR__).'/src'
            );
        }

        $this->app->register(FormBuilderServiceProvider::class);

        $this->artisan('migrate', [
            '--path' => 'modules/_bundled/glitter-form_builder/database/migrations',
            '--realpath' => false,
            '--force' => true,
        ])->assertSuccessful();

        $this->app->make(ModuleManager::class)->syncModulePermissions(new Module);
    }
}
