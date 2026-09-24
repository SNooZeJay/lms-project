<?php

namespace App\Enums;

enum UserAccountStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
