<?php

namespace Modules\Glitter\FormBuilder\Enums;

enum RetentionMode: string
{
    case Manual = 'manual';
    case Forever = 'forever';
    case Days = 'days';
}
