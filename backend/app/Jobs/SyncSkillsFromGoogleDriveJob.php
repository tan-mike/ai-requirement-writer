<?php

namespace App\Jobs;

use App\Models\Persona;
use App\Services\GoogleDriveSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\YamlFrontMatter\YamlFrontMatter;

class SyncSkillsFromGoogleDriveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(GoogleDriveSyncService $driveService): void
    {
        $folderId = config('services.google.drive.folder_id');
        Log::info("SyncSkillsFromGoogleDriveJob: Starting job. Folder ID: {$folderId}");

        if (!$folderId) {
            Log::warning("SyncSkillsFromGoogleDriveJob: No Google Drive folder ID set in configuration.");
            return;
        }

        try {
            $files = $driveService->listFiles($folderId);
            Log::info("SyncSkillsFromGoogleDriveJob: Found " . count($files) . " markdown files to sync.");

            DB::transaction(function () use ($driveService, $files) {
                foreach ($files as $file) {
                    Log::info("SyncSkillsFromGoogleDriveJob: Syncing file - ID: {$file->id}, Name: {$file->name}");
                    
                    $content = $driveService->getFileContent($file->id);
                    Log::info("SyncSkillsFromGoogleDriveJob: File content size: " . strlen($content) . " bytes.");
                    
                    $document = YamlFrontMatter::parse($content);

                    $data = [
                        'name' => $document->matter('name', Str::title(str_replace('.md', '', $file->name))),
                        'role' => $document->matter('role', 'lead'),
                        'cost_multiplier' => (float) $document->matter('cost_multiplier', 1.0),
                        'is_global' => (bool) $document->matter('is_global', true),
                        'is_active' => (bool) $document->matter('is_active', true),
                        'system_prompt' => trim($document->body()),
                    ];

                    Log::info("SyncSkillsFromGoogleDriveJob: Parsed frontmatter data: " . json_encode([
                        'name' => $data['name'],
                        'role' => $data['role'],
                        'is_global' => $data['is_global'],
                        'is_active' => $data['is_active'],
                        'system_prompt_preview' => Str::limit($data['system_prompt'], 50),
                    ]));

                    $persona = Persona::firstOrNew(['google_drive_id' => $file->id]);
                    $exists = $persona->exists;

                    $persona->fill([
                        'name' => $data['name'],
                        'role' => $data['role'],
                        'system_prompt' => $data['system_prompt'],
                        'cost_multiplier' => $data['cost_multiplier'],
                        'is_global' => $data['is_global'],
                        'is_active' => $data['is_active'],
                    ]);

                    if (!$persona->exists) {
                        $slug = Str::slug($data['name']);
                        
                        // If slug exists, append short drive ID for stability
                        if (Persona::where('slug', $slug)->exists()) {
                            $slug .= '-' . substr($file->id, 0, 5);
                        }
                        
                        $persona->slug = $slug;
                    }

                    $persona->save();
                    Log::info("SyncSkillsFromGoogleDriveJob: Successfully " . ($exists ? "updated" : "created") . " Persona database entry. ID: {$persona->id}, Slug: {$persona->slug}");
                }
            });

            Log::info("SyncSkillsFromGoogleDriveJob: Finished job successfully.");
        } catch (\Exception $e) {
            Log::error("SyncSkillsFromGoogleDriveJob: Job failed with exception: " . $e->getMessage(), [
                'exception' => $e
            ]);
            throw $e;
        }
    }
}
