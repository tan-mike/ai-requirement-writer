<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requirement_drafts', function (Blueprint $table) {
            // MySQL might be using the unique index for the project_id FK.
            // Add a dedicated index for project_id first.
            $table->index('project_id');
        });

        Schema::table('requirement_drafts', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('project_id')->constrained()->cascadeOnDelete();
            $table->dropUnique(['project_id', 'type', 'version']);
            $table->unique(['project_id', 'type', 'version', 'user_id'], 'req_drafts_proj_type_ver_user_unique');
        });
    }

    public function down(): void
    {
        Schema::table('requirement_drafts', function (Blueprint $table) {
            $table->dropUnique('req_drafts_proj_type_ver_user_unique');
            $table->unique(['project_id', 'type', 'version']);
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('requirement_drafts', function (Blueprint $table) {
            $table->dropIndex(['project_id']);
        });
    }
};
