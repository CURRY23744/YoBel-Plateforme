<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum FileCategory: string
{
    use HasValues;

    case Specification = 'specification';
    case Mockup = 'mockup';
    case Contract = 'contract';
    case Deliverable = 'deliverable';
    case Report = 'report';
    case Image = 'image';
    case Other = 'other';
}
