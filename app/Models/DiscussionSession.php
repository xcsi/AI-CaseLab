<?php

namespace App\Models;

use App\Enums\DiscussionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DiscussionSession extends Model
{
    use HasFactory;

    /**
     * Mirrors the migration's column defaults so an in-memory model matches
     * the database immediately after create(), without needing a refresh().
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'round_count' => 0,
    ];

    protected $fillable = [
        'discussable_type',
        'discussable_id',
        'persona',
        'status',
        'round_count',
        'max_rounds',
        'started_at',
        'ended_at',
        'outcome_summary',
    ];

    protected function casts(): array
    {
        return [
            'status' => DiscussionStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * The subject under discussion — a CaseAttempt for every session Version 2
     * ships, and (per docs/13-ai-discussion-engine-design.md §1.5) a different
     * model entirely for future subject types, without any schema change here.
     */
    public function discussable(): MorphTo
    {
        return $this->morphTo();
    }

    public function turns(): HasMany
    {
        return $this->hasMany(DiscussionTurn::class);
    }
}
