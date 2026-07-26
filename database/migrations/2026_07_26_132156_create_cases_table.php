<?php

use App\Enums\CaseStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('ticket_content');
            $table->string('difficulty');
            $table->unsignedInteger('estimated_minutes');
            $table->string('status')->default(CaseStatus::Draft->value);
            $table->unsignedInteger('version')->default(1);
            $table->text('model_solution_summary')->nullable();
            $table->decimal('max_score', 5, 2)->default(0);
            $table->boolean('allow_reattempt')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'category_id', 'difficulty']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};
