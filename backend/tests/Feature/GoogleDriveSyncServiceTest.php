<?php

namespace Tests\Feature;

use App\Services\GoogleDriveSyncService;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\Resource\Files;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\FileList;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Mockery;

class GoogleDriveSyncServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_initializes_with_service_account()
    {
        Config::set('services.google.drive', [
            'auth_method' => 'service_account',
            'service_account_json' => json_encode([
                'type' => 'service_account',
                'project_id' => 'test-project',
                'private_key_id' => '123',
                'private_key' => 'abc',
                'client_email' => 'test@example.com',
                'client_id' => '456',
                'auth_uri' => 'https://example.com/auth',
                'token_uri' => 'https://example.com/token',
                'auth_provider_x509_cert_url' => 'https://example.com/certs',
                'client_x509_cert_url' => 'https://example.com/x509',
            ]),
        ]);

        $service = new GoogleDriveSyncService();
        $this->assertInstanceOf(GoogleDriveSyncService::class, $service);
    }

    public function test_list_files_returns_array_of_files()
    {
        $mockClient = Mockery::mock(Client::class);
        $mockDrive = Mockery::mock(Drive::class);
        $mockFilesResource = Mockery::mock(Files::class);
        $mockFileList = Mockery::mock(FileList::class);
        $mockFile = Mockery::mock(DriveFile::class);

        $mockFile->shouldReceive('getId')->andReturn('file-123');
        $mockFile->shouldReceive('getName')->andReturn('test.md');
        
        $mockFileList->shouldReceive('getFiles')->andReturn([$mockFile]);
        
        $mockFilesResource->shouldReceive('listFiles')->with(Mockery::on(function($args) {
            return str_contains($args['q'], "'folder-123' in parents") 
                && str_contains($args['q'], "mimeType = 'text/markdown'");
        }))->andReturn($mockFileList);

        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('getScopes')->andReturn(['https://www.googleapis.com/auth/drive.readonly']);
        $mockDrive->shouldReceive('getClient')->andReturn($mockClient);
        
        $mockDrive->files = $mockFilesResource;

        // We need a way to inject the mock drive into the service
        $service = new GoogleDriveSyncService($mockDrive);
        
        $files = $service->listFiles('folder-123');
        
        $this->assertIsArray($files);
        $this->assertCount(1, $files);
        $this->assertEquals('file-123', $files[0]->getId());
    }

    public function test_get_file_content_returns_body()
    {
        $mockDrive = Mockery::mock(Drive::class);
        $mockFilesResource = Mockery::mock(Files::class);
        $mockResponse = Mockery::mock(\Psr\Http\Message\ResponseInterface::class);
        $mockStream = Mockery::mock(\Psr\Http\Message\StreamInterface::class);

        $mockStream->shouldReceive('getContents')->andReturn('# Test Content');
        $mockResponse->shouldReceive('getBody')->andReturn($mockStream);
        
        $mockFilesResource->shouldReceive('get')->with('file-123', ['alt' => 'media'])
            ->andReturn($mockResponse);

        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('getScopes')->andReturn(['https://www.googleapis.com/auth/drive.readonly']);
        $mockDrive->shouldReceive('getClient')->andReturn($mockClient);
        
        $mockDrive->files = $mockFilesResource;

        $service = new GoogleDriveSyncService($mockDrive);
        
        $content = $service->getFileContent('file-123');
        
        $this->assertEquals('# Test Content', $content);
    }
}
