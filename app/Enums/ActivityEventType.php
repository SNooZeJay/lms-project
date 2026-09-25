<?php

namespace App\Enums;

enum ActivityEventType: string
{
    case RoleChanged = 'role_changed';
    case AccountStatusChanged = 'account_status_changed';
}
