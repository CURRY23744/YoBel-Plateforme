<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum Role: string
{
    use HasValues;

    case Admin = 'admin';
    case ProjectManager = 'project_manager';
    case Member = 'member';
    case Guest = 'guest';
}
