<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum EventType: string
{
    use HasValues;

    case Meeting = 'meeting';
    case Delivery = 'delivery';
    case ClientMeeting = 'client_meeting';
    case Reminder = 'reminder';
    case Deadline = 'deadline';
    case Other = 'other';
}
