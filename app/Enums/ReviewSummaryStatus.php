<?php

namespace App\Enums;

enum ReviewSummaryStatus: string
{
    case INSUFFICIENT_REVIEWS = 'insufficient_reviews';
    case PENDING = 'pending';
    case GENERATING = 'generating';
    case READY = 'ready';
    case UNAVAILABLE = 'unavailable';
}
