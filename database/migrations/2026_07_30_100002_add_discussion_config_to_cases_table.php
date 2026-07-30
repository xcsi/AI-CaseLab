<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->boolean('discussion_enabled')->default(false)->after('allow_reattempt');
            // Validated against config('discussion_personas') keys at the application layer,
            // not a DB enum — same reasoning as discussion_sessions.persona (see
            // docs/13-ai-discussion-engine-design.md §9.1 and §9.3).
            $table->string('discussion_default_persona')->nullable()->after('discussion_enabled');
            $table->unsignedInteger('discussion_max_rounds')->nullable()->after('discussion_default_persona');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn(['discussion_enabled', 'discussion_default_persona', 'discussion_max_rounds']);
        });
    }
};
