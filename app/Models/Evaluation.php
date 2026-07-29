<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'reviewed_at',
        'reviewed_by',
        'instructor_comment',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'evaluated_at' => 'datetime',
            'reviewed_at' => 'datetime',
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

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * True while at least one criterion is still `pending_manual_review`
     * (ManualReviewStrategy's stub result) and hasn't received an
     * instructor_score yet. Derived from existing data on purpose —
     * no separate status column to drift out of sync.
     */
    public function needsInstructorReview(): bool
    {
        if ($this->reviewed_at !== null) {
            return false;
        }

        return $this->criterionResults->contains(
            fn (EvaluationCriterionResult $result) => ($result->metadata['pending_manual_review'] ?? false)
                && $result->instructor_score === null
        );
    }

    /**
     * @param  Builder<Evaluation>  $query
     * @return Builder<Evaluation>
     */
    public function scopeAwaitingInstructorReview(Builder $query): Builder
    {
        return $query->whereNull('reviewed_at')->whereHas(
            'criterionResults',
            fn ($q) => $q->whereNull('instructor_score')->where('metadata->pending_manual_review', true)
        );
    }
}
