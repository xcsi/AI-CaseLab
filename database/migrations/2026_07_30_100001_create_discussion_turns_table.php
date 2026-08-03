<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussion_turns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discussion_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence_order');
            $table->string('role');
            $table->text('content');
            $table->string('verdict')->nullable();
            $table->text('internal_note')->nullable();
            $table->json('evidence_referenced')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->json('fallback_log')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['discussion_session_id', 'sequence_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussion_turns');
    }
};
