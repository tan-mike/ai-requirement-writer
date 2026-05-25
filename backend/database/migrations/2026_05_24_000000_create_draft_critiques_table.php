<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draft_critiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requirement_draft_id')->constrained()->cascadeOnDelete();
            $table->foreignId('persona_id')->constrained()->cascadeOnDelete();
            $table->longText('content')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->unique(['requirement_draft_id', 'persona_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draft_critiques');
    }
};
