<?php

namespace App\Enums;

enum ReviewStatus: string
{
    case PUBLISHED = 'published';
    case HIDDEN = 'hidden';
}
