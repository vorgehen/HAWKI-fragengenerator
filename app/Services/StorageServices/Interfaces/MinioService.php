<?php

namespace App\Services\StorageServices\Interfaces;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class MinioService implements StorageServiceInterface
{


    protected string $bucket;
    protected string $disk;

    public function __construct()
    {
        $this->bucket = config('filesystems.disks.minio.bucket', 'hawki-files');
        $this->disk = 'minio';
    }

    /**
     * Store a file in MinIO storage
     * 
     * @param UploadedFile|string $file The file to store (either UploadedFile instance or file contents)
     * @param string $filename The name to save the file as
     * @param string|null $category Optional category/bucket to store the file in
     * @return bool Whether the file was successfully stored
     */

    public function storeFile($file, string $filename, string $uuid, string $category): bool
    {

        $path = $this->buildPath($category, $uuid, $filename);

        try {
            $contents = ($file instanceof UploadedFile)
                ? file_get_contents($file->getRealPath())
                : $file;

            // Optionally, store with correct mime type
            $mimeType = ($file instanceof UploadedFile) ? $file->getMimeType() : null;

            $options = [
                'visibility' => 'public',
            ];

            if ($mimeType) {
                $options['ContentType'] = $mimeType;
            }

            $result = Storage::disk($this->disk)->put($path, $contents, $options);

            if (!$result) {
                Log::error("Failed to store file [$path] in bucket [{$this->bucket}]");
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error("MinIO upload error: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }



    /**
     * Retrieve the FIRST main file of specified type from the uuid folder (not output).
     * e.g. $fileType = 'pdf', 'doc', 'docx'
     */
    public function retrieveFile(string $uuid, string $category)
    {
        $category = $category ?? 'default';
        $folder = $this->buildFolder($category, $uuid);

        // Find matching files in {category}/{uuid} (excluding output/)
        $files = Storage::disk($this->disk)->files($folder);
        $file = $files[0];
        if($file){
            return Storage::disk($this->disk)->get($file);
        }

        return false;
    }

    /**
     * Delete the ENTIRE {category}/{uuid} folder with all files (main and output).
     */
    public function deleteFile(string $uuid, string $category): bool
    {
        $folder = $this->buildFolder($category, $uuid);
        return Storage::disk($this->disk)->deleteDirectory($folder);
    }



    /**
     * Generate public URL for the FIRST main file of a given type
     */
    public function getFileUrl(string $uuid, string $category): ?string
    {
        $folder = $this->buildFolder($category, $uuid);
        $files = Storage::disk($this->disk)->files($folder);
        if(count($files) === 0){
            return null;
        }
        $file = $files[0];
        if (dirname($file) === $folder) {
            return Storage::disk($this->disk)->temporaryUrl($file, now()->addMinutes(1));
        }
        return null;
    }


    /// EXTRA FUNCTIONS
    /**
     * Delete the "output" folder and all its contents for a specific file (UUID/category).
     *
     * @param string $uuid
     * @param string|null $category
     * @return bool True if the folder and all its contents were successfully deleted, false otherwise.
     */
    public function deleteConvertedFiles(string $uuid, ?string $category = null): bool
    {
        $category = $category ?? 'default';
        $outputFolder = $this->buildFolder($category, $uuid) . '/output';

        try {
            // deleteDirectory returns true if successful or if directory does not exist
            $result = Storage::disk($this->disk)->deleteDirectory($outputFolder);
            if (!$result) {
                Log::warning("Failed to delete output folder [$outputFolder] in bucket [{$this->bucket}]");
            }
            return $result;
        } catch (\Throwable $e) {
            Log::error("Failed to delete output folder: " . $e->getMessage(), ['outputFolder' => $outputFolder]);
            throw $e;
        }
    }


    /**
     * Generate public URLs for all output files of a given type
     * @return string[]
     */
    public function getOutputFilesUrls(string $uuid, ?string $category = null, string $fileType): array
    {
        $category = $category ?? 'default';
        $outputFolder = $this->buildFolder($category, $uuid) . '/output';
        $files = Storage::disk($this->disk)->files($outputFolder);

        $urls = [];
        foreach ($files as $file) {
            if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === strtolower($fileType)) {
                $urls[] = Storage::disk($this->disk)->url($file);
            }
        }
        return $urls;
    }



    /**
     * Retrieve all output files (files in uuid/output/) with the specified extension (type).
     * e.g. $fileType = 'md', 'png', 'jpg'
     * @return array [ [ 'path' => ..., 'contents' => ...], ... ]
     */
    public function getOutputFilesByType(string $uuid, string $category, string $fileType)
    {
        $category = $category ?? 'default';
        $outputFolder = $this->buildFolder($category, $uuid) . '/output';
        $files = Storage::disk($this->disk)->files($outputFolder);

        $matches = [];
        foreach ($files as $file) {
            if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === strtolower($fileType)) {
                $matches[] = [
                    'path' => $file,
                    'contents' => Storage::disk($this->disk)->get($file),
                ];
            }
        }
        return $matches;
    }


    /**
     * Build the folder path for a given category/uuid (no trailing slash)
     */
    private function buildFolder(string $category, string $uuid): string
    {
        return trim($category, '/') . '/' . trim($uuid, '/');
    }

    /**
     * Build the full file path for storage.
     */
    protected function buildPath(string $category, string $uuid, string $name): string
    {
        return trim($category, '/') . '/' . trim($uuid, '/') . '/' . trim($name, '/');
    }

}