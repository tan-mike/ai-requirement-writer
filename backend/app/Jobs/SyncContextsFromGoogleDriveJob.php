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
use Illuminate\Support\Str;
use Spatie\YamlFrontMatter\YamlFrontMatter;

class SyncContextsFromGoogleDriveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(GoogleDriveSyncService $driveService): void
    {
        $folderId = config('services.google.drive.contexts_folder_id');
        if (!$folderId) return;

        $files = $driveService->listFiles($folderId);

        DB::transaction(function () use ($driveService, $files) {
            foreach ($files as $file) {
                $content = $driveService->getFileContent($file->id);
                $document = YamlFrontMatter::parse($content);

                $data = [
                    'name' => $document->matter('name', Str::title(str_replace('.md', '', $file->name))),
                    'type' => $document->matter('type', 'general'),
                    'is_global' => (bool) $document->matter('is_global', true),
                    'content' => trim($document->body()),
                ];

                ProjectContext::updateOrCreate(
                    ['google_drive_id' => $file->id],
                    $data
                );
            }
        });
    }
}
