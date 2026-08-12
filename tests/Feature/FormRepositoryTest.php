<?php

namespace Modules\Glitter\FormBuilder\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Glitter\FormBuilder\Enums\FormStatus;
use Modules\Glitter\FormBuilder\Repositories\Contracts\FormRepositoryInterface;
use Modules\Glitter\FormBuilder\Tests\ModuleTestCase;

final class FormRepositoryTest extends ModuleTestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootPhaseTwoDatabaseModule();
    }

    public function test_repository_persists_numeric_id_uuid_and_draft_defaults(): void
    {
        $form = app(FormRepositoryInterface::class)->create([
            'slug' => 'repository-form',
            'title' => ['ko' => '저장소 폼', 'en' => 'Repository form'],
            'status' => FormStatus::Draft,
            'draft_schema' => ['schemaVersion' => '1.0', 'fields' => []],
        ]);

        self::assertIsInt($form->id);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $form->uuid);
        self::assertSame($form->id, app(FormRepositoryInterface::class)->findOrFailByUuid($form->uuid)->id);
    }
}
