<?php

namespace Modules\Glitter\FormBuilder\Schema\ValueObjects;

final readonly class FormSchema
{
    /**
     * @param list<array<string, mixed>> $fields
     * @param array<string, mixed>|null $form
     * @param list<array<string, mixed>> $providerSnapshot
     * @param list<string> $topLevelKeys
     * @param list<SchemaValidationIssue> $parsingIssues
     */
    public function __construct(
        public ?string $schemaVersion,
        public array $fields,
        public ?array $form,
        public array $providerSnapshot,
        public ?string $publishedAt,
        public array $topLevelKeys,
        public ?int $encodedBytes,
        public array $parsingIssues,
    ) {}
}
