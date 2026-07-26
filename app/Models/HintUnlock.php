<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HintUnlock extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'case_attempt_id',
        'hint_id',
        'penalty_applied',
        'unlocked_at',
    ];

    protected function casts(): array
    {
        return [
            'unlocked_at' => 'datetime',
        ];
    }

    public function caseAttempt(): BelongsTo
    {
        return $this->belongsTo(CaseAttempt::class);
    }

    public function hint(): BelongsTo
    {
        return $this->belongsTo(Hint::class);
    }
}
