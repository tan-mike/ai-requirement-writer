<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_generation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('project_id')->nullable()->constrained()->onDelete('set null');
            $table->string('type'); // brd, stories, spec, critique, discovery, synthesis, architecture
            $table->string('model');
            $table->longText('system_prompt')->nullable();
            $table->longText('input_prompt')->nullable();
            $table->longText('output_text')->nullable();
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->integer('total_tokens')->nullable();
            $table->integer('execution_time_ms')->nullable();
            $table->string('status')->default('success'); // success, failure
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'type']);
            $table->index(['project_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generation_logs');
    }
};
