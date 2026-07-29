<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationCriterionResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluation_id',
        'rubric_criterion_id',
        'score_awarded',
        'max_score',
        'feedback_text',
        'instructor_score',
        'instructor_comment',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function rubricCriterion(): BelongsTo
    {
        return $this->belongsTo(RubricCriterion::class);
    }

    /**
     * The score that actually counts — the instructor's override when
     * present, otherwise whatever the strategy originally produced.
     * score_awarded is never overwritten, so this is the only place that
     * needs to know which one wins.
     */
    public function effectiveScore(): float
    {
        return (float) ($this->instructor_score ?? $this->score_awarded);
    }

    public function isPendingManualReview(): bool
    {
        return ($this->metadata['pending_manual_review'] ?? false) && $this->instructor_score === null;
    }
}
