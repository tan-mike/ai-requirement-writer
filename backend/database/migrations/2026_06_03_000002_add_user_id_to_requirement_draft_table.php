<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requirement_drafts', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('project_id')->constrained()->cascadeOnDelete();
            $table->dropUnique(['project_id', 'type', 'version']);
            $table->unique(['project_id', 'type', 'version', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('requirement_drafts', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'type', 'version', 'user_id']);
            $table->unique(['project_id', 'type', 'version']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
