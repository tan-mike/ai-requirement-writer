<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Illuminate\Support\Facades\Config;

class GoogleDriveSyncService
{
    protected Drive $service;

    public function __construct(?Drive $service = null)
    {
        if ($service) {
            $this->service = $service;
            return;
        }

        $client = new Client();
        $config = Config::get('services.google.drive');

        if (($config['auth_method'] ?? 'service_account') === 'oauth') {
            $client->setClientId($config['client_id']);
            $client->setClientSecret($config['client_secret']);
            $client->refreshToken($config['refresh_token']);
        } else {
            $auth = $config['service_account_json'];
            if (is_string($auth) && file_exists($auth)) {
                $client->setAuthConfig($auth);
            } elseif (is_string($auth)) {
                $client->setAuthConfig(json_decode($auth, true));
            } else {
                $client->setAuthConfig($auth);
            }
        }

        $client->addScope(Drive::DRIVE_READONLY);
        $this->service = new Drive($client);
    }

    public function listFiles(string $folderId): array
    {
        $query = "'$folderId' in parents and mimeType = 'text/markdown' and trashed = false";
        $results = $this->service->files->listFiles([
            'q' => $query,
            'fields' => 'files(id, name, modifiedTime)'
        ]);

        return $results->getFiles();
    }

    public function getFileContent(string $fileId): string
    {
        $response = $this->service->files->get($fileId, ['alt' => 'media']);
        return $response->getBody()->getContents();
    }
}
