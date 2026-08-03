<?php

namespace App\Models;

use App\Enums\DiscussionTurnRole;
use App\Enums\DiscussionVerdict;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscussionTurn extends Model
{
    use HasFactory;

    /**
     * discussion_turns has created_at only (no updated_at) — turns are
     * immutable once persisted — so Eloquent's automatic timestamp
     * management is disabled the same way ActivityLog already does it;
     * the column's DB-level useCurrent() default populates it instead.
     */
    public $timestamps = false;

    protected $fillable = [
        'discussion_session_id',
        'sequence_order',
        'role',
        'content',
        'verdict',
        'internal_note',
        'evidence_referenced',
        'prompt_tokens',
        'completion_tokens',
        'provider',
        'model',
        'fallback_log',
    ];

    protected function casts(): array
    {
        return [
            'role' => DiscussionTurnRole::class,
            'verdict' => DiscussionVerdict::class,
            'evidence_referenced' => 'array',
            'fallback_log' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(DiscussionSession::class, 'discussion_session_id');
    }
}
