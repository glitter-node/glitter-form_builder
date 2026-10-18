<?php

namespace Modules\Glitter\FormBuilder\Enums;

enum FormStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
