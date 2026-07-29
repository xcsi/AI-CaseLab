<?php

namespace App\Evaluation\Contracts;

use App\Evaluation\CriterionResult;
use App\Models\Diagnosis;
use App\Models\RubricCriterion;

interface EvaluationStrategyInterface
{
    /**
     * Scores one rubric criterion against a submitted diagnosis. Any
     * implementation must be substitutable for another wherever the
     * interface is type-hinted (Liskov) — resolved per criterion by
     * EvaluationStrategyResolver, never chosen by the caller directly.
     */
    public function evaluate(RubricCriterion $criterion, Diagnosis $diagnosis): CriterionResult;
}
