<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the Gemini-only personal key with provider-agnostic AI credentials.
 *
 * Users who already saved a Gemini key are pinned to the Gemini OpenAI-compatible
 * endpoint and the model they were using, so their setup keeps working even if the
 * platform default provider changes later.
 */
return new class extends Migration
{
    private const GEMINI_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/openai';
    private const PREVIOUS_MODEL = 'gemma-4-26b-a4b-it';

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('gemini_api_key', 'ai_api_key');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('ai_base_url', 500)->nullable()->after('ai_api_key');
            $table->string('ai_model', 255)->nullable()->after('ai_base_url');
        });

        DB::table('users')->whereNotNull('ai_api_key')->update([
            'ai_base_url' => self::GEMINI_BASE_URL,
            'ai_model' => self::PREVIOUS_MODEL,
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ai_base_url', 'ai_model']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('ai_api_key', 'gemini_api_key');
        });
    }
};
