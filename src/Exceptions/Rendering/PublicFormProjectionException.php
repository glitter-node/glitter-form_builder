<?php

namespace Modules\Glitter\FormBuilder\Exceptions\Rendering;

use RuntimeException;
use Throwable;

final class PublicFormProjectionException extends RuntimeException
{
    public function __construct(
        public readonly string $formUuid,
        public readonly string $slug,
        public readonly int $versionId,
        public readonly ?string $versionUuid,
        public readonly string $category,
        Throwable $previous,
    ) {
        parent::__construct('Published form projection failed.', 0, $previous);
    }
}
