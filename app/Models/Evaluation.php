<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_attempt_id',
        'diagnosis_id',
        'total_score',
        'max_score',
        'feedback_summary',
        'strategy_used',
        'metadata',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'evaluated_at' => 'datetime',
        ];
    }

    public function caseAttempt(): BelongsTo
    {
        return $this->belongsTo(CaseAttempt::class);
    }

    public function diagnosis(): BelongsTo
    {
        return $this->belongsTo(Diagnosis::class);
    }

    public function criterionResults(): HasMany
    {
        return $this->hasMany(EvaluationCriterionResult::class);
    }
}
