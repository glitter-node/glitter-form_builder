<?php

namespace Modules\Glitter\FormBuilder\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Modules\Glitter\FormBuilder\Exceptions\Schema\FormSchemaValidationException;
use Modules\Glitter\FormBuilder\Models\FormVersion;
use Modules\Glitter\FormBuilder\Services\FormService;
use Modules\Glitter\FormBuilder\Tests\ModuleTestCase;

final class FormServiceTest extends ModuleTestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootPhaseTwoDatabaseModule();
        Auth::login(User::query()->whereHas('roles', fn ($query) => $query->where('identifier', 'admin'))->firstOrFail());
    }

    public function test_publish_creates_new_immutable_revision_and_keeps_slug(): void
    {
        $service = app(FormService::class);
        $form = $service->create([
            'slug' => 'service-form',
            'title' => ['ko' => '서비스 폼', 'en' => 'Service form'],
            'draft_schema' => ['schemaVersion' => '1.0', 'fields' => []],
            'retention_acknowledged' => true,
        ]);

        self::assertSame(1, $service->publish($form)->revision);
        self::assertSame(2, $service->publish($form->fresh())->revision);
        self::assertSame(2, FormVersion::query()->where('form_id', $form->id)->count());
        self::assertSame('service-form', $form->fresh()->slug);
    }

    public function test_manual_retention_cannot_persist_days(): void
    {
        $service = app(FormService::class);
        $form = $service->create([
            'slug' => 'manual-retention',
            'title' => ['ko' => '수동 보존', 'en' => 'Manual retention'],
            'draft_schema' => ['schemaVersion' => '1.0', 'fields' => []],
            'retention_mode' => 'manual',
            'retention_days' => 30,
        ]);

        self::assertNull($form->retention_days);
        self::assertNull($service->update($form, ['retention_days' => 90])->retention_days);
    }

    public function test_locked_publish_validation_failure_rolls_back_without_version_or_state_change(): void
    {
        $service = app(FormService::class);
        $form = $service->create([
            'slug' => 'invalid-locked-publish',
            'title' => ['ko' => '잘못된 폼', 'en' => 'Invalid form'],
            'draft_schema' => ['schemaVersion' => '1.0', 'fields' => [['key' => 'field', 'type' => 'missing:type']]],
            'retention_acknowledged' => true,
        ]);

        try {
            $service->publish($form);
            self::fail('Expected locked publish validation failure.');
        } catch (FormSchemaValidationException) {
            self::assertSame(0, FormVersion::query()->where('form_id', $form->id)->count());
            self::assertSame('draft', $form->fresh()->status->value);
            self::assertNull($form->fresh()->current_published_version_id);
        }
    }
}
