<?php

namespace Modules\Glitter\FormBuilder\Tests\Unit\Schema;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Glitter\FormBuilder\Enums\FormAccessMode;
use Modules\Glitter\FormBuilder\Enums\FormStatus;
use Modules\Glitter\FormBuilder\Enums\RetentionMode;
use Modules\Glitter\FormBuilder\Exceptions\Schema\FormSchemaValidationException;
use Modules\Glitter\FormBuilder\Fields\BuiltIn\BuiltInFieldProviders;
use Modules\Glitter\FormBuilder\Fields\FieldTypeRegistry;
use Modules\Glitter\FormBuilder\Models\Form;
use Modules\Glitter\FormBuilder\Models\FormVersion;
use Modules\Glitter\FormBuilder\Repositories\Contracts\FormRepositoryInterface;
use Modules\Glitter\FormBuilder\Repositories\Contracts\FormVersionRepositoryInterface;
use Modules\Glitter\FormBuilder\Schema\FormSchemaParser;
use Modules\Glitter\FormBuilder\Schema\FormSchemaValidator;
use Modules\Glitter\FormBuilder\Schema\PublishSchemaValidator;
use Modules\Glitter\FormBuilder\Services\FormService;
use PHPUnit\Framework\TestCase;

final class FormServicePublishValidationTest extends TestCase
{
    public function test_existing_draft_with_legacy_null_consent_is_validated_with_canonical_semantics(): void
    {
        $container = new Container;
        $container->instance('config', new ConfigRepository(['app' => ['locale' => 'en']]));
        Container::setInstance($container);

        $form = new Form;
        $form->forceFill([
            'title' => ['en' => 'Consent form'],
            'description' => [],
            'draft_schema' => ['schemaVersion' => '1.0', 'fields' => [[
                'key' => 'consent_1', 'type' => 'consent', 'provider' => 'glitter-form_builder', 'contractVersion' => '1.0',
                'label' => ['en' => 'Consent'], 'description' => [], 'required' => true, 'default' => false,
                'options' => [], 'validation' => [], 'config' => ['statementId' => null],
                'sensitive' => false, 'indexable' => false, 'exportable' => true,
            ]]],
        ]);
        $service = new class($this->createStub(FormRepositoryInterface::class), $this->createStub(FormVersionRepositoryInterface::class), new FormSchemaParser, self::validator()) extends FormService
        {
            protected function assertScope(Form $form, string $action): void {}
        };

        $result = $service->validatePublishSchema($form);

        self::assertTrue($result->isValid(), json_encode($result->toArray(), JSON_THROW_ON_ERROR));
    }

    public function test_invalid_schema_stops_before_persistence(): void
    {
        $container = new Container;
        $container->instance('config', new ConfigRepository(['app' => ['locale' => 'en']]));
        Container::setInstance($container);

        $locked = new class extends Form
        {
            private array $values;

            public function __construct()
            {
                $this->values = ['id' => 1, 'uuid' => '00000000-0000-0000-0000-000000000001', 'title' => ['en' => 'Locked'], 'description' => [], 'status' => FormStatus::Draft, 'retention_mode' => RetentionMode::Manual, 'access_mode' => FormAccessMode::AuthenticatedOnly, 'retention_acknowledged_at' => new \DateTimeImmutable, 'draft_schema' => ['schemaVersion' => '1.0', 'fields' => [['key' => 'x', 'type' => 'missing:type']]]];
            }

            public function getAttribute($key): mixed
            {
                return $this->values[$key] ?? null;
            }
        };
        $forms = new class($locked) implements FormRepositoryInterface
        {
            public int $locks = 0;

            public int $updates = 0;

            public function __construct(private Form $locked) {}

            public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
            {
                throw new \LogicException;
            }

            public function findOrFailByUuid(string $uuid): Form
            {
                throw new \LogicException;
            }

            public function lockById(int $id): Form
            {
                $this->locks++;

                return $this->locked;
            }

            public function create(array $data): Form
            {
                throw new \LogicException;
            }

            public function update(Form $form, array $data): Form
            {
                $this->updates++;

                return $form;
            }

            public function delete(Form $form): bool
            {
                throw new \LogicException;
            }
        };
        $versions = new class implements FormVersionRepositoryInterface
        {
            public int $creates = 0;

            public function nextRevision(Form $form): int
            {
                throw new \LogicException;
            }

            public function create(array $data): FormVersion
            {
                $this->creates++;
                throw new \LogicException;
            }
        };
        $validator = self::validator();
        $service = new class($forms, $versions, new FormSchemaParser, $validator) extends FormService
        {
            protected function assertScope(Form $form, string $action): void {}
        };
        $form = new class extends Form
        {
            public function getAttribute($key): mixed
            {
                return ['id' => 1, 'uuid' => '00000000-0000-0000-0000-000000000001', 'title' => ['en' => 'Stale valid form'], 'description' => [], 'draft_schema' => ['schemaVersion' => '1.0', 'fields' => []]][$key] ?? null;
            }
        };

        DB::swap(new class
        {
            public function transaction(callable $callback): mixed
            {
                return $callback();
            }
        });

        try {
            $service->publish($form);
            self::fail('Expected schema validation exception.');
        } catch (FormSchemaValidationException $exception) {
            self::assertTrue($exception->result()->hasErrors());
        }
        self::assertSame(1, $forms->locks);
        self::assertSame(0, $forms->updates);
        self::assertSame(0, $versions->creates);

        $valid = new Form;
        $valid->forceFill([
            'title' => ['en' => 'Form'],
            'description' => [],
            'draft_schema' => ['schemaVersion' => '1.0', 'fields' => [[
                'key' => 'email', 'type' => 'email', 'provider' => 'glitter-form_builder', 'contractVersion' => '1.0',
                'label' => ['en' => 'Email'], 'required' => false, 'sensitive' => true, 'indexable' => false, 'exportable' => true,
            ]]],
        ]);
        $warningOnly = $service->validatePublishSchema($valid);
        self::assertTrue($warningOnly->isValid());
        self::assertSame([], $warningOnly->warningCodes());
    }

    private static function validator(): PublishSchemaValidator
    {
        $registry = new FieldTypeRegistry;
        BuiltInFieldProviders::register($registry);
        $registry->seal();

        return new PublishSchemaValidator(new FormSchemaValidator, $registry);
    }
}
