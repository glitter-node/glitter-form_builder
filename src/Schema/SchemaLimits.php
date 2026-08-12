<?php

namespace Modules\Glitter\FormBuilder\Schema;

final class SchemaLimits
{
    public const MAX_ENCODED_BYTES = 262144;
    public const MAX_FIELDS = 100;
    public const MAX_OPTIONS_PER_FIELD = 100;
    public const MAX_LOCALES = 20;
    public const MAX_LABEL_LENGTH = 255;
    public const MAX_DESCRIPTION_LENGTH = 2000;
    public const MAX_NESTING_DEPTH = 16;
    public const MAX_NODES = 10000;

    private function __construct() {}
}
