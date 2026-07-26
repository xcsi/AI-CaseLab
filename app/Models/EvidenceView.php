<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidenceView extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'case_attempt_id',
        'evidence_item_id',
        'view_count',
        'first_viewed_at',
        'last_viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'first_viewed_at' => 'datetime',
            'last_viewed_at' => 'datetime',
        ];
    }

    public function caseAttempt(): BelongsTo
    {
        return $this->belongsTo(CaseAttempt::class);
    }

    public function evidenceItem(): BelongsTo
    {
        return $this->belongsTo(EvidenceItem::class);
    }
}
