<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CaseAttempt;
use App\Models\Hint;
use App\Services\HintUnlockService;
use Illuminate\Http\JsonResponse;

class HintController extends Controller
{
    public function __construct(
        private readonly HintUnlockService $hintUnlocks,
    ) {}

    public function unlock(CaseAttempt $attempt, Hint $hint): JsonResponse
    {
        abort_unless($hint->case_id === $attempt->case_id, 404);

        $unlock = $this->hintUnlocks->unlock($attempt, $hint);

        return response()->json([
            'status' => 'ok',
            'hint_id' => $hint->id,
            'content' => $hint->content,
            'penalty_applied' => $unlock->penalty_applied,
            'max_possible_score' => $attempt->max_possible_score,
        ]);
    }
}
