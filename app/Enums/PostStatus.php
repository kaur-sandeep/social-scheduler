<?php

namespace App\Enums;

enum PostStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Queued = 'queued';
    case Publishing = 'publishing';
    case Retrying = 'retrying';
    case Published = 'published';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
