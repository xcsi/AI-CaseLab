<?php

namespace App\Services;

use App\Models\CaseAttempt;
use App\Models\Hint;
use App\Models\HintUnlock;
use Illuminate\Support\Facades\DB;

class HintUnlockService
{
    /**
     * Idempotent: unlocking an already-unlocked hint returns the existing
     * record without re-applying the penalty.
     */
    public function unlock(CaseAttempt $attempt, Hint $hint): HintUnlock
    {
        return DB::transaction(function () use ($attempt, $hint) {
            $existing = HintUnlock::where('case_attempt_id', $attempt->id)
                ->where('hint_id', $hint->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $unlock = HintUnlock::create([
                'case_attempt_id' => $attempt->id,
                'hint_id' => $hint->id,
                'penalty_applied' => $hint->score_penalty,
                'unlocked_at' => now(),
            ]);

            $attempt->update([
                'max_possible_score' => max(0, $attempt->max_possible_score - $hint->score_penalty),
            ]);

            return $unlock;
        });
    }
}
