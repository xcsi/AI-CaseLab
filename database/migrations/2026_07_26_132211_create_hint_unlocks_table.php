<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hint_unlocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hint_id')->constrained()->cascadeOnDelete();
            $table->decimal('penalty_applied', 5, 2);
            $table->timestamp('unlocked_at')->useCurrent();

            $table->unique(['case_attempt_id', 'hint_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hint_unlocks');
    }
};
