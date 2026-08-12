<?php

namespace Modules\Glitter\FormBuilder\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Glitter\FormBuilder\Tests\ModuleTestCase;

final class AdminFormApiTest extends ModuleTestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootPhaseTwoDatabaseModule();
        Route::prefix('api/modules/glitter-form_builder')
            ->middleware(SubstituteBindings::class)
            ->group(function (): void {
                require dirname(__DIR__, 2).'/src/routes/api.php';
            });
        $this->actingAs(User::query()->whereHas('roles', fn ($query) => $query->where('identifier', 'admin'))->firstOrFail(), 'sanctum');
    }

    public function test_admin_can_create_read_update_publish_and_archive_form(): void
    {
        $base = '/api/modules/glitter-form_builder/admin/forms';
        $created = $this->postJson($base, [
            'slug' => 'api-form',
            'title' => ['ko' => 'API 폼', 'en' => 'API form'],
            'draft_schema' => ['schemaVersion' => '1.0', 'fields' => []],
            'retention_acknowledged' => true,
        ])->assertCreated();

        $uuid = $created->json('data.uuid');
        $this->getJson($base.'?search=api-form')->assertOk()->assertJsonPath('data.data.0.title', 'API 폼');
        app()->setLocale('en');
        $this->getJson($base.'?search=api-form')->assertOk()->assertJsonPath('data.data.0.title', 'API form');
        app()->setLocale('ko');
        $this->getJson($base.'/'.$uuid)->assertOk();
        $this->putJson($base.'/'.$uuid, ['title' => ['ko' => '수정 폼', 'en' => 'Updated form']])->assertOk();
        $this->postJson($base.'/'.$uuid.'/publish')->assertOk()->assertJsonPath('data.revision', 1);
        $this->deleteJson($base.'/'.$uuid)->assertOk();
    }
}
