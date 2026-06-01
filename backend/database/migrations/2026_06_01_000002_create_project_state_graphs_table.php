<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('project_state_graphs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('current_node'); // drafting, reviewing, refining, approved, committed
            $table->json('context_data'); // The Blackboard (shared state)
            $table->boolean('is_paused')->default(false);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('project_state_graphs');
    }
};
