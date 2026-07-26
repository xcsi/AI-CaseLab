<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_attempt_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('diagnosis_id')->constrained()->cascadeOnDelete();
            $table->decimal('total_score', 5, 2);
            $table->decimal('max_score', 5, 2);
            $table->text('feedback_summary')->nullable();
            $table->string('strategy_used');
            $table->json('metadata')->nullable();
            $table->timestamp('evaluated_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
