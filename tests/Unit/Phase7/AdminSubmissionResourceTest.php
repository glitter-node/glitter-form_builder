<?php

namespace Modules\Glitter\FormBuilder\Tests\Unit\Phase7;

use Illuminate\Http\Request;
use Modules\Glitter\FormBuilder\Enums\SubmissionActorType;
use Modules\Glitter\FormBuilder\Enums\SubmissionStatus;
use Modules\Glitter\FormBuilder\Http\Resources\AdminSubmissionDetailResource;
use Modules\Glitter\FormBuilder\Models\Form;
use Modules\Glitter\FormBuilder\Models\FormVersion;
use Modules\Glitter\FormBuilder\Models\Submission;
use PHPUnit\Framework\TestCase;

final class AdminSubmissionResourceTest extends TestCase
{
    public function test_detail_resource_resolves_once_and_projects_redacted_typed_fields(): void
    {
        $form = new Form;
        $form->forceFill(['uuid' => '019fba5a-dcc4-7397-8aa6-09299a7fc03e', 'slug' => 'phase7', 'title' => ['ko' => 'Phase 7']]);
        $version = new FormVersion;
        $version->forceFill(['revision' => 1, 'snapshot' => ['fields' => [
            ['key' => 'text_1', 'type' => 'text', 'label' => ['ko' => '이름'], 'sensitive' => false],
            ['key' => 'email_1', 'type' => 'email', 'label' => ['ko' => '이메일'], 'sensitive' => true],
            ['key' => 'consent_1', 'type' => 'consent', 'label' => ['ko' => '동의'], 'sensitive' => false],
        ]]]);
        $submission = new Submission;
        $submission->forceFill([
            'uuid' => '019fbab8-e9c5-7000-8000-000000000001',
            'status' => SubmissionStatus::Received,
            'actor_type' => SubmissionActorType::Guest,
            'normalized_payload' => [
                'text_1' => 'Browser User',
                'email_1' => 'sensitive@example.com',
                'consent_1' => ['accepted' => true, 'statementId' => 'consent'],
            ],
        ]);
        $submission->setRelation('form', $form);
        $submission->setRelation('formVersion', $version);
        $submission->setRelation('submittedUser', null);

        $resolved = (new AdminSubmissionDetailResource($submission))->resolve(new Request);

        self::assertSame($submission->uuid, $resolved['uuid']);
        self::assertSame('Browser User', $resolved['fields'][0]['display_value']);
        self::assertSame('••••••', $resolved['fields'][1]['display_value']);
        self::assertSame('accepted', $resolved['fields'][2]['display_value']);
        self::assertArrayNotHasKey('normalized_payload', $resolved);
    }
}
