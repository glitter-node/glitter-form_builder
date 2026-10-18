<?php

namespace Modules\Glitter\FormBuilder\Enums;

enum SubmissionActorType: string
{
    case Guest = 'guest';
    case Authenticated = 'authenticated';
    case Admin = 'admin';
}
