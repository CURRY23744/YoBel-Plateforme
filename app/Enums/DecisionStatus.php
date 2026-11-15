<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum DecisionStatus: string
{
    use HasValues;

    case Proposed = 'proposed';
    case Validated = 'validated';
    case Rejected = 'rejected';
    case Superseded = 'superseded';
}
