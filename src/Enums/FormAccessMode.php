<?php

namespace Modules\Glitter\FormBuilder\Enums;

enum FormAccessMode: string
{
    case AuthenticatedOnly = 'authenticated_only';
    case GuestAndAuthenticated = 'guest_and_authenticated';
    case AdminOnly = 'admin_only';
}
