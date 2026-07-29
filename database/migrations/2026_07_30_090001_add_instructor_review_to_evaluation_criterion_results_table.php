<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_criterion_results', function (Blueprint $table) {
            // Nullable override, kept separate from score_awarded so the
            // strategy's original output is never overwritten — score_awarded
            // stays the auditable record of what the algorithm produced,
            // instructor_score (when present) is what actually counts.
            $table->decimal('instructor_score', 5, 2)->nullable()->after('feedback_text');
            $table->text('instructor_comment')->nullable()->after('instructor_score');
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_criterion_results', function (Blueprint $table) {
            $table->dropColumn(['instructor_score', 'instructor_comment']);
        });
    }
};
