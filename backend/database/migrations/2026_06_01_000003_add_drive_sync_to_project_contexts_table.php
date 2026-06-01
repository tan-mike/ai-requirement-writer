<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('project_contexts', function (Blueprint $table) {
            $table->string('google_drive_id')->nullable()->unique()->after('id');
            $table->boolean('is_global')->default(false)->after('type');
        });
    }

    public function down(): void {
        Schema::table('project_contexts', function (Blueprint $table) {
            $table->dropColumn(['google_drive_id', 'is_global']);
        });
    }
};
