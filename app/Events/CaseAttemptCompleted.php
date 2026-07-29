<?php

namespace App\Events;

use App\Models\CaseAttempt;
use App\Models\Evaluation;

class CaseAttemptCompleted
{
    public function __construct(
        public readonly CaseAttempt $attempt,
        public readonly Evaluation $evaluation,
    ) {}
}
