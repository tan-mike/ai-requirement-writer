<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('personas', function (Blueprint $table) {
            // Check if column exists before adding it (idempotency)
            if (!Schema::hasColumn('personas', 'google_drive_id')) {
                $table->string('google_drive_id')->nullable()->unique()->after('id');
            }
        });
    }

    public function down(): void {
        Schema::table('personas', function (Blueprint $table) {
            $table->dropColumn(['google_drive_id']);
        });
    }
};
