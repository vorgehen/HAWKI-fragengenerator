<?php

namespace App\Services\StorageServices\Interfaces;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class MinioService implements StorageServiceInterface
{
    /**
     * Store a file in MinIO storage
     * 
     * @param UploadedFile|string $file The file to store (either UploadedFile instance or file contents)
     * @param string $filename The name to save the file as
     * @param string|null $category Optional category/bucket to store the file in
     * @return bool Whether the file was successfully stored
     */
    public function storeFile($file, string $filename, ?string $category = null): bool
    {
        $bucket = $this->resolveBucket($category);
        
        // Handle different file input types
        if ($file instanceof UploadedFile) {
            $contents = file_get_contents($file->getRealPath());
            return Storage::disk('minio')->put($filename, $contents, [
                'bucket' => $bucket,
                'visibility' => 'private',
            ]);
        } else {
            return Storage::disk('minio')->put($filename, $file, [
                'bucket' => $bucket,
                'visibility' => 'private',
            ]);
        }
    }

    /**
     * Retrieve a file from MinIO storage
     * 
     * @param string $filename The name of the file to retrieve
     * @param string|null $category Optional category/bucket the file is stored in
     * @return string|null The file contents or null if not found
     */
    public function retrieveFile(string $filename, ?string $category = null): ?string
    {
        $bucket = $this->resolveBucket($category);

        if (Storage::disk('minio')->exists($filename, ['bucket' => $bucket])) {
            return Storage::disk('minio')->get($filename, ['bucket' => $bucket]);
        }

        return null;
    }

    /**
     * Delete a file from MinIO storage
     * 
     * @param string $filename The name of the file to delete
     * @param string|null $category Optional category/bucket the file is stored in
     * @return bool Whether the file was successfully deleted
     */
    public function deleteFile(string $filename, ?string $category = null): bool
    {
        $bucket = $this->resolveBucket($category);

        if (Storage::disk('minio')->exists($filename, ['bucket' => $bucket])) {
            return Storage::disk('minio')->delete($filename, ['bucket' => $bucket]);
        }

        // File does not exist
        return false; 
    }
    
    /**
     * Get the public URL for a stored file
     * 
     * @param string $filename The name of the file
     * @param string|null $category Optional category/bucket the file is stored in
     * @return string|null The public URL or null if file not found
     */
    public function getFileUrl(string $filename, ?string $category = null): ?string
    {
        $bucket = $this->resolveBucket($category);
        
        if (Storage::disk('minio')->exists($filename, ['bucket' => $bucket])) {
            return Storage::disk('minio')->temporaryUrl(
                $filename, 
                now()->addMinutes(config('filesystems.disks.minio.url_expiry', 60)),
                ['bucket' => $bucket]
            );
        }
        
        return null;
    }
    
    /**
     * Generate a unique filename with the original extension
     * 
     * @param string $originalFilename The original filename
     * @return string A unique filename
     */
    public function generateUniqueFilename(string $originalFilename): string
    {
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        return Str::uuid() . ($extension ? '.' . $extension : '');
    }

    /**
     * Resolve the bucket name based on category
     * 
     * @param string|null $category
     * @return string
     */
    protected function resolveBucket(?string $category): string
    {
        return $category && config("minio.buckets.$category")
            ? config("minio.buckets.$category")
            : config('filesystems.disks.minio.bucket');
    }
}