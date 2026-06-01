<?php

namespace App\Jobs;

use App\Models\Persona;
use App\Services\GoogleDriveSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Spatie\YamlFrontMatter\YamlFrontMatter;

class SyncSkillsFromGoogleDriveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(GoogleDriveSyncService $driveService): void
    {
        $folderId = config('services.google.drive.folder_id');
        $files = $driveService->listFiles($folderId);

        foreach ($files as $file) {
            $content = $driveService->getFileContent($file->id);
            $document = YamlFrontMatter::parse($content);

            $data = [
                'name' => $document->matter('name', Str::title(str_replace('.md', '', $file->name))),
                'role' => $document->matter('role', 'lead'),
                'cost_multiplier' => (float) $document->matter('cost_multiplier', 1.0),
                'is_global' => (bool) $document->matter('is_global', true),
                'is_active' => (bool) $document->matter('is_active', true),
                'system_prompt' => trim($document->body()),
            ];

            Persona::updateOrCreate(
                ['google_drive_id' => $file->id],
                [
                    'name' => $data['name'],
                    'role' => $data['role'],
                    'system_prompt' => $data['system_prompt'],
                    'cost_multiplier' => $data['cost_multiplier'],
                    'is_global' => $data['is_global'],
                    'is_active' => $data['is_active'],
                    'slug' => Persona::where('google_drive_id', $file->id)->value('slug') 
                              ?? Str::slug($data['name'] . '-' . Str::random(5)),
                ]
            );
        }
    }
}
