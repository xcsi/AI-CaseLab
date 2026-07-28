<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CaseAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotebookController extends Controller
{
    public function update(Request $request, CaseAttempt $attempt): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['nullable', 'string', 'max:20000'],
        ]);

        $note = $attempt->investigationNote()->updateOrCreate(
            ['case_attempt_id' => $attempt->id],
            ['content' => $validated['content'] ?? null],
        );

        return response()->json([
            'status' => 'ok',
            'saved_at' => $note->updated_at->toIso8601String(),
        ]);
    }
}
