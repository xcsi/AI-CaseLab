<?php

namespace App\Models;

use App\Enums\MatchingType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RubricCriterion extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id',
        'title',
        'description',
        'weight',
        'matching_type',
        'expected_data',
    ];

    protected function casts(): array
    {
        return [
            'matching_type' => MatchingType::class,
            'expected_data' => 'array',
            'weight' => 'decimal:2',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    public function criterionResults(): HasMany
    {
        return $this->hasMany(EvaluationCriterionResult::class);
    }
}
