<?php

namespace Modules\Glitter\FormBuilder\Contracts\Fields;

use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldDefinition;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldIndexValue;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldMetadata;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldNormalizationResult;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldRendererDescriptor;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldValidationContext;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldValidationDescriptor;

interface FieldProvider
{
    public const CONTRACT_VERSION = '1.0';

    public function type(): string;

    public function providerId(): string;

    public function contractVersion(): string;

    public function metadata(): FieldMetadata;

    /** @return array<string, mixed> JSON-safe configuration descriptor. */
    public function configurationSchema(): array;

    public function validateDefinition(FieldDefinition $field): void;

    public function validationDescriptor(FieldDefinition $field, FieldValidationContext $context): FieldValidationDescriptor;

    public function normalize(mixed $value, FieldDefinition $field, FieldValidationContext $context): FieldNormalizationResult;

    public function serialize(FieldNormalizationResult $result): mixed;

    public function display(FieldNormalizationResult $result, string $locale): string;

    public function export(FieldNormalizationResult $result, string $locale): string|int|bool|null;

    public function projectIndex(FieldNormalizationResult $result, FieldDefinition $field): ?FieldIndexValue;

    public function rendererDescriptor(): FieldRendererDescriptor;
}
