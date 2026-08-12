<?php

namespace Modules\Glitter\FormBuilder\Schema\ValueObjects;

use Modules\Glitter\FormBuilder\Schema\Enums\SchemaIssueSeverity;

final readonly class SchemaValidationResult
{
    /** @param list<SchemaValidationIssue> $issues */
    public function __construct(private array $issues) {}

    public function isValid(): bool { return ! $this->hasErrors(); }
    public function hasErrors(): bool { return $this->errors() !== []; }
    public function hasWarnings(): bool { return $this->warnings() !== []; }
    /** @return list<SchemaValidationIssue> */
    public function issues(): array { return $this->issues; }
    /** @return list<SchemaValidationIssue> */
    public function errors(): array { return array_values(array_filter($this->issues, static fn (SchemaValidationIssue $issue): bool => $issue->severity === SchemaIssueSeverity::Error)); }
    /** @return list<SchemaValidationIssue> */
    public function warnings(): array { return array_values(array_filter($this->issues, static fn (SchemaValidationIssue $issue): bool => $issue->severity === SchemaIssueSeverity::Warning)); }
    /** @return list<string> */
    public function errorCodes(): array { return array_map(static fn (SchemaValidationIssue $issue): string => $issue->code, $this->errors()); }
    /** @return list<string> */
    public function warningCodes(): array { return array_map(static fn (SchemaValidationIssue $issue): string => $issue->code, $this->warnings()); }
    /** @return array{errors: list<array<string, mixed>>, warnings: list<array<string, mixed>>} */
    public function toArray(?callable $translator = null): array
    {
        $map = static fn (SchemaValidationIssue $issue): array => $issue->toArray($translator);
        return ['errors' => array_map($map, $this->errors()), 'warnings' => array_map($map, $this->warnings())];
    }
}
