<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class GoogleDriveSyncService
{
    protected Drive $service;

    public function __construct(?Drive $service = null)
    {
        if ($service && $service->getClient() && $service->getClient()->getScopes()) {
            $this->service = $service;
            return;
        }

        $client = new Client();
        $config = Config::get('services.google.drive');

        if (($config['auth_method'] ?? 'service_account') === 'oauth') {
            Log::debug($config);
            $client->setClientId($config['client_id']);
            $client->setClientSecret($config['client_secret']);
            $client->refreshToken($config['refresh_token']);
        } else {
            $auth = $config['service_account_json'];
            $authConfig = null;
            if (is_string($auth)) {
                if (file_exists($auth)) {
                    $json = file_get_contents($auth);
                    $authConfig = json_decode($json, true);
                } else {
                    $authConfig = json_decode($auth, true);
                }
            } elseif (is_array($auth)) {
                $authConfig = $auth;
            }
            if (is_array($authConfig) && isset($authConfig['private_key'])) {
                // Reconstruct private key if it was split into array keys due to multiline parsing issues
                $standardKeys = [
                    'type', 'project_id', 'private_key_id', 'private_key',
                    'client_email', 'client_id', 'auth_uri', 'token_uri',
                    'auth_provider_x509_cert_url', 'client_x509_cert_url',
                    'universe_domain'
                ];

                $pkLines = [$authConfig['private_key']];
                foreach ($authConfig as $key => $value) {
                    if (!in_array($key, $standardKeys)) {
                        $pkLines[] = $key;
                    }
                }

                if (count($pkLines) > 1) {
                    $authConfig['private_key'] = implode("\n", $pkLines);
                    foreach ($authConfig as $key => $value) {
                        if (!in_array($key, $standardKeys)) {
                            unset($authConfig[$key]);
                        }
                    }
                }

                $authConfig['private_key'] = trim($authConfig['private_key'], "\"'");
                $authConfig['private_key'] = str_replace('\\n', "\n", $authConfig['private_key']);
                $client->setAuthConfig($authConfig);
            } else {
                if (is_string($auth) && file_exists($auth)) {
                    $client->setAuthConfig($auth);
                } elseif (is_string($auth)) {
                    $client->setAuthConfig(json_decode($auth, true));
                } else {
                    $client->setAuthConfig($auth);
                }
            }
        }

        $client->addScope(Drive::DRIVE_READONLY);
        $this->service = new Drive($client);
    }

    public function listFiles(string $folderId): array
    {
        $query = "'$folderId' in parents and trashed = false";
        $results = $this->service->files->listFiles([
            'q' => $query,
            'fields' => 'files(id, name, modifiedTime)'
        ]);

        return array_filter($results->getFiles(), function ($file) {
            return str_ends_with(strtolower($file->name), '.md');
        });
    }

    public function getFileContent(string $fileId): string
    {
        $response = $this->service->files->get($fileId, ['alt' => 'media']);
        return $response->getBody()->getContents();
    }
}
