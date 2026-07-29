<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnosis_evidence_citations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagnosis_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evidence_item_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['diagnosis_id', 'evidence_item_id'], 'diagnosis_evidence_citations_unique');
            $table->index('evidence_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnosis_evidence_citations');
    }
};
