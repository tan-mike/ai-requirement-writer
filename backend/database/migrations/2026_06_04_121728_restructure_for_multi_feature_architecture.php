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
        // 1. Update Projects table - remove feature-specific fields
        Schema::table('projects', function (Blueprint $table) {
            // Check if columns exist before dropping (to be safe if original migration was already modified)
            if (Schema::hasColumn('projects', 'template_id')) {
                $table->dropConstrainedForeignId('template_id');
            }
            if (Schema::hasColumn('projects', 'lead_persona_id')) {
                $table->dropConstrainedForeignId('lead_persona_id');
            }
            if (Schema::hasColumn('projects', 'mode')) {
                $table->dropColumn('mode');
            }
            
            // Simplify status
            $table->string('status')->default('active')->change();
        });

        // 2. Update Requirement Drafts to link to Feature instead of Project
        Schema::table('requirement_drafts', function (Blueprint $table) {
            // Drop indexes first (before dropping columns they reference)
            if (Schema::hasColumn('requirement_drafts', 'project_id')) {
                $table->dropIndex('requirement_drafts_project_id_index');
            }
            $table->dropUnique('req_drafts_proj_type_ver_user_unique');
            
            // Now safe to drop column and add new structure
            if (Schema::hasColumn('requirement_drafts', 'project_id')) {
                $table->dropConstrainedForeignId('project_id');
            }
            $table->foreignId('feature_id')->after('id')->constrained()->cascadeOnDelete();
            
            // Update unique index
            $table->unique(['feature_id', 'type', 'version', 'user_id'], 'req_drafts_feat_type_ver_user_unique');
        });

        // 3. Rename Project Intakes to Feature Intakes and link to Feature
        Schema::table('project_intakes', function (Blueprint $table) {
            // Drop unique index before dropping the column
            $table->dropUnique('project_intakes_project_id_unique');
            $table->dropConstrainedForeignId('project_id');
        });
        Schema::rename('project_intakes', 'feature_intakes');
        Schema::table('feature_intakes', function (Blueprint $table) {
            $table->foreignId('feature_id')->after('id')->unique()->constrained()->cascadeOnDelete();
        });

        // 4. Update Chat Messages to link to Feature
        Schema::table('chat_messages', function (Blueprint $table) {
            // Drop any indexes on project_id before dropping
            $table->dropConstrainedForeignId('project_id');
            $table->foreignId('feature_id')->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->after('feature_id')->constrained()->nullOnDelete();
            $table->dropColumn('order');
        });

        // 5. Update AI Generation Logs to link to Feature
        Schema::table('ai_generation_logs', function (Blueprint $table) {
            // Drop composite index before dropping project_id column
            $table->dropIndex('ai_generation_logs_project_id_type_index');
            $table->dropConstrainedForeignId('project_id');
            $table->foreignId('feature_id')->after('id')->constrained()->cascadeOnDelete();
        });

        // 6. Restructure Project State Graphs to link to Feature
        Schema::table('project_state_graphs', function (Blueprint $table) {
            // Drop unique index before dropping project_id column
            $table->dropUnique('project_state_graphs_project_id_unique');
            $table->dropConstrainedForeignId('project_id');
        });
        Schema::rename('project_state_graphs', 'feature_state_graphs');
        Schema::table('feature_state_graphs', function (Blueprint $table) {
            $table->foreignId('feature_id')->after('id')->unique()->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse is complex, but since we're wiping, we don't strictly need it for now.
        // However, for completeness:

        Schema::table('feature_state_graphs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('feature_id');
            $table->foreignId('project_id')->after('id')->unique()->constrained()->cascadeOnDelete();
        });
        Schema::rename('feature_state_graphs', 'project_state_graphs');
        
        Schema::table('ai_generation_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('feature_id');
            $table->foreignId('project_id')->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('feature_id');
            $table->foreignId('project_id')->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('feature_intakes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('feature_id');
            $table->foreignId('project_id')->after('id')->unique()->constrained()->cascadeOnDelete();
        });
        Schema::rename('feature_intakes', 'project_intakes');

        Schema::table('requirement_drafts', function (Blueprint $table) {
            $table->dropUnique('req_drafts_feat_type_ver_user_unique');
            $table->dropConstrainedForeignId('feature_id');
            $table->foreignId('project_id')->after('id')->constrained()->cascadeOnDelete();
            $table->unique(['project_id', 'type', 'version', 'user_id'], 'req_drafts_proj_type_ver_user_unique');
            $table->index('project_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_persona_id')->nullable()->constrained('personas')->nullOnDelete();
            $table->enum('mode', ['template', 'conversational'])->default('template');
            $table->enum('status', ['draft', 'in_progress', 'complete'])->default('draft')->change();
        });
    }
};
