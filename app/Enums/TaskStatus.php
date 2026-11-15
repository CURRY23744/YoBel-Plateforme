<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum TaskStatus: string
{
    use HasValues;

    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Review = 'review';
    case Done = 'done';
    case Cancelled = 'cancelled';
}
