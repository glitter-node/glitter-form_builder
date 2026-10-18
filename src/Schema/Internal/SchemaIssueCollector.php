<?php

namespace Modules\Glitter\FormBuilder\Schema\Internal;

use Modules\Glitter\FormBuilder\Schema\Enums\SchemaIssueSeverity;
use Modules\Glitter\FormBuilder\Schema\ValueObjects\SchemaValidationIssue;
use Modules\Glitter\FormBuilder\Schema\ValueObjects\SchemaValidationResult;

final class SchemaIssueCollector
{
    /** @var list<SchemaValidationIssue> */
    private array $issues = [];

    /** @param array<string, bool|int|string> $parameters */
    public function error(string $code, string $path, array $parameters = []): void { $this->add($code, $path, $parameters, SchemaIssueSeverity::Error); }
    /** @param array<string, bool|int|string> $parameters */
    public function warning(string $code, string $path, array $parameters = []): void { $this->add($code, $path, $parameters, SchemaIssueSeverity::Warning); }
    public function append(SchemaValidationIssue $issue): void { $this->issues[] = $issue; }
    public function result(): SchemaValidationResult { return new SchemaValidationResult($this->issues); }

    private function add(string $code, string $path, array $parameters, SchemaIssueSeverity $severity): void
    {
        $group = $severity === SchemaIssueSeverity::Error ? 'errors' : 'warnings';
        $key = str_replace('.', '_', $code);
        $this->issues[] = new SchemaValidationIssue($code, $path, "glitter-form_builder::schema.{$group}.{$key}", $parameters, $severity);
    }
}
