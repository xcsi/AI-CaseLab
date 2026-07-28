<?php

namespace App\Http\Middleware;

use App\Models\CaseAttempt;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAttemptBelongsToUser
{
    /**
     * Handle an incoming request.
     *
     * Prevents a student from opening someone else's case_attempt by
     * guessing an ID — every route scoped to a {attempt} parameter must
     * belong to the authenticated user.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $attempt = $request->route('attempt');

        if ($attempt instanceof CaseAttempt && $attempt->user_id !== $request->user()?->id) {
            abort(403);
        }

        return $next($request);
    }
}
