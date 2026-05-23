<?php

use App\Models\Persona;
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
        $mandate = "\n\nCRITICAL MANDATE: Output ONLY the markdown document. Do NOT include any conversational preamble, internal reasoning, or concluding remarks. Start your response immediately with the first markdown heading (e.g., '# ' or '## ').";

        $personas = Persona::where('role', 'lead')->get();

        foreach ($personas as $persona) {
            if (!str_contains($persona->system_prompt, 'CRITICAL MANDATE')) {
                $persona->update([
                    'system_prompt' => $persona->system_prompt . $mandate
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No easy way to reverse this cleanly without potential data loss if users edited,
        // but we could try to strip the mandate string.
    }
};
