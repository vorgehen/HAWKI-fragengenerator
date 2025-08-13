<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\StorageServices\StorageServiceFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Throwable;



class CheckStorageConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:storage {--filesystem=s3 : The filesystem to test (s3, local, nextcloud)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Storage Connection for specified filesystem';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filesystem = $this->option('filesystem');
        
        $this->info("Testing {$filesystem} storage connection...");
        
        $result = match($filesystem) {
            's3' => $this->checkS3WriteAccess(),
            'local' => $this->checkLocalWriteAccess(),
            'nextcloud' => $this->checkNextCloudConnection(),
            default => ['success' => false, 'message' => "Unsupported filesystem: {$filesystem}"]
        };
        
        if($result['success']){
            $this->info($result['message']);
        } else {
            $this->error($result['message']);
        }
    }




    public function checkS3WriteAccess(string $disk = 's3', string $testFileName = 's3_test.txt'): array
    {
        $content = "S3 connection test: " . now();

        try {
            // Step 1: Upload test file
            $uploadSuccess = Storage::disk($disk)->put($testFileName, $content);
            if (!$uploadSuccess) {
                return [
                    'success' => false,
                    'message' => "Upload failed — check write permissions."
                ];
            }

            // Step 2: Verify file exists
            if (!Storage::disk($disk)->exists($testFileName)) {
                return [
                    'success' => false,
                    'message' => "Upload succeeded but file not found — possible visibility/ACL issue."
                ];
            }

            // Step 3: Cleanup
            Storage::disk($disk)->delete($testFileName);

            return [
                'success' => true,
                'message' => "S3 connection and write test succeeded."
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => "S3 test failed: " . $e->getMessage()
            ];
        }
    }

    public function checkLocalWriteAccess(string $disk = 'data_repo', string $testFileName = 'local_test.txt'): array
    {
        $content = "Local storage test: " . now();

        try {
            // Step 1: Upload test file
            $uploadSuccess = Storage::disk($disk)->put($testFileName, $content);
            if (!$uploadSuccess) {
                return [
                    'success' => false,
                    'message' => "Upload failed — check write permissions."
                ];
            }

            // Step 2: Verify file exists
            if (!Storage::disk($disk)->exists($testFileName)) {
                return [
                    'success' => false,
                    'message' => "Upload succeeded but file not found — possible file system issue."
                ];
            }

            // Step 3: Read back content
            $retrievedContent = Storage::disk($disk)->get($testFileName);
            if ($retrievedContent !== $content) {
                return [
                    'success' => false,
                    'message' => "Content mismatch — file corruption or encoding issue."
                ];
            }

            // Step 4: Cleanup
            Storage::disk($disk)->delete($testFileName);

            return [
                'success' => true,
                'message' => "Local storage connection and write test succeeded."
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => "Local storage test failed: " . $e->getMessage()
            ];
        }
    }

    public function checkNextCloudConnection(): array
    {
        $baseUrl = config('filesystems.disks.nextcloud.base_url');
        $username = config('filesystems.disks.nextcloud.username');
        $password = config('filesystems.disks.nextcloud.password');
        $basePath = config('filesystems.disks.nextcloud.base_path', '/');

        // Step 1: Check configuration
        if (empty($baseUrl) || empty($username) || empty($password)) {
            return [
                'success' => false,
                'message' => 'NextCloud configuration incomplete. Check NEXTCLOUD_BASE_URL, NEXTCLOUD_USERNAME, and NEXTCLOUD_PASSWORD environment variables.'
            ];
        }

        try {
            // Step 2: Test WebDAV connection with PROPFIND
            $webdavUrl = rtrim($baseUrl, '/') . '/remote.php/dav/files/' . $username . '/' . trim($basePath, '/');
            
            $response = Http::withBasicAuth($username, $password)
                ->withHeaders(['Depth' => '0'])
                ->timeout(10)
                ->send('PROPFIND', $webdavUrl);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => "NextCloud WebDAV connection failed. Status: {$response->status()}. Response: " . $response->body()
                ];
            }

            // Step 3: Test file upload using StorageServiceFactory
            $storageService = StorageServiceFactory::create('nextcloud');
            $testContent = "NextCloud connection test: " . now();
            $testUuid = 'test-' . uniqid();
            $testFilename = 'connection_test.txt';
            $testCategory = 'connection-test';

            $uploadResult = $storageService->storeFile($testContent, $testFilename, $testUuid, $testCategory);
            
            if (!$uploadResult) {
                return [
                    'success' => false,
                    'message' => 'NextCloud connection succeeded but file upload failed.'
                ];
            }

            // Step 4: Test file retrieval with debugging
            $this->info("Testing file retrieval...");
            $retrievedContent = $storageService->retrieveFile($testUuid, $testCategory);
            
            if ($retrievedContent === false) {
                // Debug: Try to list the directory manually
                $debugFolderPath = trim($testCategory, '/') . '/' . trim($testUuid, '/');
                $debugWebdavUrl = rtrim($baseUrl, '/') . '/remote.php/dav/files/' . $username . '/' . trim($basePath, '/') . '/' . $debugFolderPath;
                
                $debugResponse = Http::withBasicAuth($username, $password)
                    ->withHeaders(['Depth' => '1'])
                    ->send('PROPFIND', $debugWebdavUrl);
                
                $debugMessage = "File upload succeeded but retrieval failed.\n";
                $debugMessage .= "Debug info:\n";
                $debugMessage .= "- Folder path: {$debugFolderPath}\n";
                $debugMessage .= "- WebDAV URL: {$debugWebdavUrl}\n";
                $debugMessage .= "- PROPFIND Status: " . $debugResponse->status() . "\n";
                $debugMessage .= "- PROPFIND Response: " . substr($debugResponse->body(), 0, 1500) . "\n";
                
                return [
                    'success' => false,
                    'message' => $debugMessage
                ];
            }

            if ($retrievedContent !== $testContent) {
                return [
                    'success' => false,
                    'message' => 'Content mismatch — file corruption or encoding issue.'
                ];
            }

            // Step 5: Cleanup
            $storageService->deleteFile($testUuid, $testCategory);

            return [
                'success' => true,
                'message' => 'NextCloud connection, upload, retrieval, and cleanup tests all succeeded.'
            ];

        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => "NextCloud test failed: " . $e->getMessage()
            ];
        }
    }



}
