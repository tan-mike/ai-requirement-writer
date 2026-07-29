<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requirement_drafts', function (Blueprint $table) {
            $table->json('context_ids')->nullable()->after('reviewer_persona_ids');
            $table->string('model')->nullable()->after('context_ids');
        });
    }

    public function down(): void
    {
        Schema::table('requirement_drafts', function (Blueprint $table) {
            $table->dropColumn(['context_ids', 'model']);
        });
    }
};
