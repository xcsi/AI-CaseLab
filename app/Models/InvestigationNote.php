<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestigationNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_attempt_id',
        'content',
    ];

    public function caseAttempt(): BelongsTo
    {
        return $this->belongsTo(CaseAttempt::class);
    }
}
