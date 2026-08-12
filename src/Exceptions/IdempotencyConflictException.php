<?php

namespace Modules\Glitter\FormBuilder\Exceptions;

use RuntimeException;

final class IdempotencyConflictException extends RuntimeException
{
    public function __construct() { parent::__construct('submission.idempotency_conflict'); }
}
