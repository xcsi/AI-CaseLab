<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLogService
{
    /**
     * Records an admin action for the "Recent Activity" dashboard widget.
     *
     * `changes` is captured as a small self-contained snapshot (e.g. the
     * subject's title) rather than a full before/after diff — enough to
     * render a readable activity line even if the subject is later
     * deleted, since `subject_type`/`subject_id` intentionally has no FK
     * constraint (Database Design Decision 6).
     *
     * @param  array<string, mixed>  $changes
     */
    public function record(string $action, Model $subject, ?User $causer, array $changes = []): void
    {
        $log = new ActivityLog([
            'causer_id' => $causer?->id,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'changes' => $changes,
        ]);

        // `created_at` isn't mass-assignable (not in $fillable — it's a
        // system-managed field, not admin input), so without setting it
        // directly the insert falls back to the `created_at` column's own
        // MySQL CURRENT_TIMESTAMP default, which reflects the DB server's
        // clock/timezone rather than the app's — causing "Recent Activity"
        // timestamps to drift from the app's actual current time.
        $log->created_at = now();
        $log->save();
    }
}
