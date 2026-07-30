<?php

namespace App\Enums;

enum DiscussionVerdict: string
{
    case Continue = 'continue';
    case Accept = 'accept';
    case EndUnresolved = 'end_unresolved';
}
