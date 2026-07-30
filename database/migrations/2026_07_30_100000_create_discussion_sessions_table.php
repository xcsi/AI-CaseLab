<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussion_sessions', function (Blueprint $table) {
            $table->id();
            $table->morphs('discussable');
            // Validated against config('discussion_personas') keys at the application layer,
            // not a DB enum — see docs/13-ai-discussion-engine-design.md §9.1 for why persona
            // is the one deliberate exception to this app's usual backed-enum convention.
            $table->string('persona');
            $table->string('status')->default('active');
            $table->unsignedInteger('round_count')->default(0);
            $table->unsignedInteger('max_rounds');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->text('outcome_summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussion_sessions');
    }
};
