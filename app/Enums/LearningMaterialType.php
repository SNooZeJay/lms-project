<?php

namespace App\Enums;

enum LearningMaterialType: string
{
    case Text = 'text';
    case Image = 'image';
    case Pdf = 'pdf';
    case Document = 'document';
    case Code = 'code';
    case VideoLink = 'video_link';
    case ExternalLink = 'external_link';
}
