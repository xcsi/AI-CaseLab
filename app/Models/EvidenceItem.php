<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvidenceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id',
        'evidence_type_id',
        'title',
        'description',
        'sequence_order',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    public function evidenceType(): BelongsTo
    {
        return $this->belongsTo(EvidenceType::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(EvidenceView::class);
    }

    public function citedInDiagnoses(): BelongsToMany
    {
        return $this->belongsToMany(Diagnosis::class, 'diagnosis_evidence_citations');
    }
}
