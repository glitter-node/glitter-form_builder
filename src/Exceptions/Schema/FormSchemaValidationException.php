<?php

namespace Modules\Glitter\FormBuilder\Exceptions\Schema;

use DomainException;
use Modules\Glitter\FormBuilder\Schema\ValueObjects\SchemaValidationResult;

final class FormSchemaValidationException extends DomainException
{
    public function __construct(private readonly SchemaValidationResult $result)
    {
        parent::__construct('form_schema.publish_validation_failed');
    }

    public function result(): SchemaValidationResult
    {
        return $this->result;
    }
}
