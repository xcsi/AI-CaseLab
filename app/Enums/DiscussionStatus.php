<?php

namespace App\Enums;

enum DiscussionStatus: string
{
    case Active = 'active';
    case Accepted = 'accepted';
    case EndedByStudent = 'ended_by_student';
    case MaxRoundsReached = 'max_rounds_reached';
}
