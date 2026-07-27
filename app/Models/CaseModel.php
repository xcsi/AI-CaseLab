<?php

namespace App\Models;

use App\Enums\CaseDifficulty;
use App\Enums\CaseStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaseModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cases';

    /**
     * Mirrors the migration's column defaults so an in-memory model matches
     * the database immediately after create(), without needing a refresh().
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'version' => 1,
        'max_score' => 0,
        'allow_reattempt' => true,
    ];

    protected $fillable = [
        'category_id',
        'created_by',
        'title',
        'slug',
        'summary',
        'ticket_content',
        'learning_outcomes',
        'difficulty',
        'estimated_minutes',
        'status',
        'version',
        'model_solution_summary',
        'max_score',
        'allow_reattempt',
    ];

    protected function casts(): array
    {
        return [
            'difficulty' => CaseDifficulty::class,
            'status' => CaseStatus::class,
            'allow_reattempt' => 'boolean',
            'max_score' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function evidenceItems(): HasMany
    {
        return $this->hasMany(EvidenceItem::class, 'case_id')->orderBy('sequence_order');
    }

    public function hints(): HasMany
    {
        return $this->hasMany(Hint::class, 'case_id')->orderBy('order_index');
    }

    public function rubricCriteria(): HasMany
    {
        return $this->hasMany(RubricCriterion::class, 'case_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(CaseAttempt::class, 'case_id');
    }

    /**
     * Published cases bump `version` whenever their content changes — the
     * case's own fields (CaseCatalogService::update) and its hints/rubric
     * criteria alike — so `case_attempts.case_version` can flag drift
     * (Database Design Decision 4). Drafts churn freely with no version cost.
     */
    public function touchVersionIfPublished(): void
    {
        if ($this->status === CaseStatus::Published) {
            $this->increment('version');
        }
    }

    /**
     * `max_score` is a denormalized sum of the case's rubric criteria
     * weights, kept in sync on every rubric change rather than computed
     * on read (Database Design: cases.max_score).
     */
    public function recalculateMaxScore(): void
    {
        $this->update(['max_score' => $this->rubricCriteria()->sum('weight')]);
    }
}
