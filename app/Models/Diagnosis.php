<?php

namespace App\Models;

use App\Enums\ConfidenceLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Diagnosis extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_attempt_id',
        'root_cause_text',
        'proposed_fix_text',
        'confidence_level',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence_level' => ConfidenceLevel::class,
            'submitted_at' => 'datetime',
        ];
    }

    public function caseAttempt(): BelongsTo
    {
        return $this->belongsTo(CaseAttempt::class);
    }

    public function citedEvidence(): BelongsToMany
    {
        return $this->belongsToMany(EvidenceItem::class, 'diagnosis_evidence_citations');
    }

    public function evaluation(): HasOne
    {
        return $this->hasOne(Evaluation::class);
    }
}
