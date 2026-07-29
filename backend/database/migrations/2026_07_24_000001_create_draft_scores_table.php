<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draft_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requirement_draft_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('criterion_scores');
            $table->decimal('overall_score', 2, 1);
            $table->json('recipe_snapshot');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draft_scores');
    }
};
