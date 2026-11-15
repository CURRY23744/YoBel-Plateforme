<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ProjectStatus: string
{
    use HasValues;

    case Idea = 'idea';
    case Feasibility = 'feasibility';
    case Validated = 'validated';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Abandoned = 'abandoned';
    case Archived = 'archived';
}
