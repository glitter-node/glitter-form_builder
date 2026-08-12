<?php

namespace Modules\Glitter\FormBuilder\Enums;

enum SubmissionValueKind: string
{
    case Text = 'text';
    case Number = 'number';
    case Boolean = 'boolean';
    case Date = 'date';
    case Datetime = 'datetime';
}
