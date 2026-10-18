<?php

namespace Modules\Glitter\FormBuilder\Exceptions;

use RuntimeException;

final class SubmissionValidationException extends RuntimeException
{
    /** @param array<string, list<string>> $errors */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('submission.validation_failed');
    }

    /** @return array<string, list<string>> */
    public function errors(): array { return $this->errors; }
}
