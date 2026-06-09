<?php

namespace App\Jobs;

use App\Models\ProjectContext;
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

class SyncContextsFromGoogleDriveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(GoogleDriveSyncService $driveService): void
    {
        $folderId = config('services.google.drive.contexts_folder_id');
        Log::info("SyncContextsFromGoogleDriveJob: Starting job. Folder ID: {$folderId}");

        if (!$folderId) {
            Log::warning("SyncContextsFromGoogleDriveJob: No Google Drive folder ID set in configuration.");
            return;
        }

        try {
            $files = $driveService->listFiles($folderId);
            Log::info("SyncContextsFromGoogleDriveJob: Found " . count($files) . " markdown files to sync.");

            DB::transaction(function () use ($driveService, $files) {
                foreach ($files as $file) {
                    Log::info("SyncContextsFromGoogleDriveJob: Syncing file - ID: {$file->id}, Name: {$file->name}");
                    
                    $content = $driveService->getFileContent($file->id);
                    Log::info("SyncContextsFromGoogleDriveJob: File content size: " . strlen($content) . " bytes.");
                    
                    $document = YamlFrontMatter::parse($content);

                    $data = [
                        'name' => $document->matter('name', Str::title(str_replace('.md', '', $file->name))),
                        'type' => $document->matter('type', 'general'),
                        'is_global' => (bool) $document->matter('is_global', true),
                        'content' => trim($document->body()),
                    ];

                    Log::info("SyncContextsFromGoogleDriveJob: Parsed frontmatter data: " . json_encode([
                        'name' => $data['name'],
                        'type' => $data['type'],
                        'is_global' => $data['is_global'],
                        'content_preview' => Str::limit($data['content'], 50),
                    ]));

                    $context = ProjectContext::updateOrCreate(
                        ['google_drive_id' => $file->id],
                        $data
                    );
                    
                    Log::info("SyncContextsFromGoogleDriveJob: Successfully synced ProjectContext database entry. ID: {$context->id}, Name: {$context->name}");
                }
            });

            Log::info("SyncContextsFromGoogleDriveJob: Finished job successfully.");
        } catch (\Exception $e) {
            Log::error("SyncContextsFromGoogleDriveJob: Job failed with exception: " . $e->getMessage(), [
                'exception' => $e
            ]);
            throw $e;
        }
    }
}
