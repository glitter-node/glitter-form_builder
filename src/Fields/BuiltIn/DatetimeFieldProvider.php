<?php

namespace Modules\Glitter\FormBuilder\Fields\BuiltIn;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Modules\Glitter\FormBuilder\Exceptions\Fields\FieldNormalizationException;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldDefinition;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldNormalizationResult;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldValidationContext;

final class DatetimeFieldProvider extends AbstractFieldProvider
{
    public function type(): string { return 'datetime'; }
    protected function storageKind(): string { return 'datetime'; }
    protected function validationKeys(): array { return ['min', 'max']; }
    protected function specificRules(FieldDefinition $field): array { return [['rule' => 'datetime_local_or_rfc3339'], ['rule' => 'canonical_timezone', 'value' => 'UTC']]; }
    public function normalize(mixed $value, FieldDefinition $field, FieldValidationContext $context): FieldNormalizationResult
    {
        if ($empty = $this->emptyOrFail($value, $field)) return $empty;
        if (! is_string($value)) throw new FieldNormalizationException('field_normalization.invalid_datetime');
        try {
            $timezone = new DateTimeZone($context->siteTimezone);
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?$/', $value)) {
                $format = strlen($value) === 16 ? '!Y-m-d\TH:i' : '!Y-m-d\TH:i:s';
                $date = DateTimeImmutable::createFromFormat($format, $value, $timezone);
                $errors = DateTimeImmutable::getLastErrors();
                if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) throw new Exception;
            } else {
                if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/', $value)) throw new Exception;
                $date = new DateTimeImmutable($value);
            }
            $canonical = $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        } catch (Exception) {
            throw new FieldNormalizationException('field_normalization.invalid_datetime');
        }
        if (isset($field->validation['min']) && $canonical < $this->canonicalBoundary((string) $field->validation['min'], $context)) throw new FieldNormalizationException('field_normalization.below_minimum');
        if (isset($field->validation['max']) && $canonical > $this->canonicalBoundary((string) $field->validation['max'], $context)) throw new FieldNormalizationException('field_normalization.above_maximum');
        return $this->result($canonical, $field);
    }
    private function canonicalBoundary(string $value, FieldValidationContext $context): string
    {
        $definition = new FieldDefinition('_boundary', 'datetime', ['en' => 'Boundary']);
        return (string) $this->normalize($value, $definition, $context)->normalizedValue;
    }
}
