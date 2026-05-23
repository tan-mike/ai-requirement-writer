<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('requirement_drafts', function (Blueprint $table) {
            $table->string('status')->default('drafting')->change();
            $table->foreignId('lead_persona_id')->nullable()->constrained('personas')->nullOnDelete();
            $table->json('reviewer_persona_ids')->nullable();
            $table->json('critiques')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requirement_drafts', function (Blueprint $table) {
            $table->enum('status', ['draft', 'approved'])->default('draft')->change();
            $table->dropConstrainedForeignId('lead_persona_id');
            $table->dropColumn(['reviewer_persona_ids', 'critiques']);
        });
    }
};
