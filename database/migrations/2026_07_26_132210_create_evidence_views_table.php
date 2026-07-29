<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evidence_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('view_count')->default(1);
            $table->timestamp('first_viewed_at')->useCurrent();
            $table->timestamp('last_viewed_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['case_attempt_id', 'evidence_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_views');
    }
};
