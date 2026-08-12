<?php

namespace Modules\Glitter\FormBuilder\Rendering\ValueObjects;

final readonly class PublicFormSchema
{
    /**
     * @param  array<string, mixed>  $form
     * @param  list<array<string, mixed>>  $fields
     * @param  list<array<string, mixed>>  $layoutFields
     */
    public function __construct(
        public string $schemaVersion,
        public array $form,
        public array $fields,
        public array $layoutFields,
        public string $publishedAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schemaVersion' => $this->schemaVersion,
            'form' => $this->form,
            'fields' => $this->fields,
            'renderer' => [
                'contractVersion' => '1.0',
                'kind' => 'g7-layout',
                'fields' => $this->layoutFields,
            ],
            'publishedAt' => $this->publishedAt,
        ];
    }
}
