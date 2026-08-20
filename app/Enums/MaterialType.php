<?php

namespace App\Enums;

enum MaterialType: string
{
    case RichText = 'rich_text';
    case Pdf = 'pdf';
    case Powerpoint = 'powerpoint';
    case ExternalUrl = 'external_url';
}
