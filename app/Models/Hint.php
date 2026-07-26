<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hint extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id',
        'order_index',
        'content',
        'score_penalty',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    public function unlocks(): HasMany
    {
        return $this->hasMany(HintUnlock::class);
    }
}
