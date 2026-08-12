<?php

namespace Modules\Glitter\FormBuilder\Schema\Enums;

enum SchemaIssueSeverity: string
{
    case Error = 'error';
    case Warning = 'warning';
}
