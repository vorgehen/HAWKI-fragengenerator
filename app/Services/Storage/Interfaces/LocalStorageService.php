<?php

namespace App\Services\Storage\Interfaces;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class LocalStorageService implements StorageServiceInterface
{

    protected string $disk;
    public function __construct(string $disk)
    {
        $this->disk = $disk ?? 'data_repo';
    }


    public function storeFile(UploadedFile|string $file, string $filename, string $uuid, ?string $category): bool
    {
        $path = $this->buildPath($category, $uuid, $filename);

        try {
            $contents = ($file instanceof UploadedFile)
                ? file_get_contents($file->getRealPath())
                : $file;

            $result = Storage::disk($this->disk)->put($path, $contents);
            if (!$result) {
                Log::error("Failed to store file [$path] in storage folder");
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error("File upload error: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }


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


    public function deleteFile(string $uuid, string $category): bool
    {
        $folder = $this->buildFolder($category, $uuid);
        $deleted = Storage::disk($this->disk)->deleteDirectory($folder);
        return $deleted;
    }



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
            // return Storage::temporaryUrl($file,  now()->addMinutes(1));
        }
        return null;
    }



    public function deleteConvertedFiles(string $uuid, ?string $category = null): bool
    {
        $category = $category ?? 'default';
        $outputFolder = $this->buildFolder($category, $uuid) . '/output';

        try {
            // deleteDirectory returns true if successful or if directory does not exist
            $result = Storage::disk($this->disk)->deleteDirectory($outputFolder);
            if (!$result) {
                Log::warning("Failed to delete output folder [$outputFolder] in repo");
            }
            return $result;
        } catch (\Throwable $e) {
            Log::error("Failed to delete output folder: " . $e->getMessage(), ['outputFolder' => $outputFolder]);
            throw $e;
        }
    }

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


    public function retrieveOutputFilesByType(string $uuid, string $category, string $fileType): array
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
