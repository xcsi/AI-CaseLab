<?php

namespace App\Models;

use App\Enums\AttemptStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CaseAttempt extends Model
{
    use HasFactory;

    /**
     * Mirrors the migration's column defaults so an in-memory model matches
     * the database immediately after create(), without needing a refresh().
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'in_progress',
        'case_version' => 1,
    ];

    protected $fillable = [
        'case_id',
        'user_id',
        'status',
        'case_version',
        'started_at',
        'submitted_at',
        'completed_at',
        'score_earned',
        'max_possible_score',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttemptStatus::class,
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function investigationNote(): HasOne
    {
        return $this->hasOne(InvestigationNote::class);
    }

    public function diagnosis(): HasOne
    {
        return $this->hasOne(Diagnosis::class);
    }

    public function evaluation(): HasOne
    {
        return $this->hasOne(Evaluation::class);
    }

    public function evidenceViews(): HasMany
    {
        return $this->hasMany(EvidenceView::class);
    }

    public function hintUnlocks(): HasMany
    {
        return $this->hasMany(HintUnlock::class);
    }
}
