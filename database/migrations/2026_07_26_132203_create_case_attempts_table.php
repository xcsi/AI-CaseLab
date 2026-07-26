<?php

use App\Enums\AttemptStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status')->default(AttemptStatus::InProgress->value);
            $table->unsignedInteger('case_version')->default(1);
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('score_earned', 5, 2)->nullable();
            $table->decimal('max_possible_score', 5, 2);
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_attempts');
    }
};
