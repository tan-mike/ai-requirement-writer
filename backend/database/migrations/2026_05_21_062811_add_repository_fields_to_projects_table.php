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
            $table->string('repository_url')->nullable()->after('status');
            $table->string('repository_path')->nullable()->after('repository_url');
            $table->longText('architecture_summary')->nullable()->after('repository_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['repository_url', 'repository_path', 'architecture_summary']);
        });
    }
};
