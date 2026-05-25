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
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        Schema::table('project_contexts', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });

        Schema::table('project_contexts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });
    }
};
