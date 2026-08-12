<?php

namespace Modules\Glitter\FormBuilder\Schema\ValueObjects;

use Modules\Glitter\FormBuilder\Schema\Enums\SchemaIssueSeverity;

final readonly class SchemaValidationIssue
{
    /** @param array<string, bool|int|string> $parameters */
    public function __construct(
        public string $code,
        public string $path,
        public string $messageKey,
        public array $parameters,
        public SchemaIssueSeverity $severity,
    ) {}

    /** @param null|callable(string, array<string, bool|int|string>): string $translator */
    public function toArray(?callable $translator = null): array
    {
        $data = [
            'code' => $this->code,
            'path' => $this->path,
            'message_key' => $this->messageKey,
            'parameters' => $this->parameters,
            'severity' => $this->severity->value,
        ];
        if ($translator !== null) {
            $data['message'] = $translator($this->messageKey, $this->parameters);
        }

        return $data;
    }
}
