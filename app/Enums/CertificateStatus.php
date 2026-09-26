<?php

namespace App\Enums;

enum CertificateStatus: string
{
    case Issued = 'issued';
    case Revoked = 'revoked';
}
