<?php

namespace App\Services\StorageServices\Interfaces;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class LocalStorageService implements StorageServiceInterface
{
    /**
     * Store a file in the local storage
     * 
     * @param UploadedFile|string $file The file to store (either UploadedFile instance or file contents)
     * @param string $filename The name to save the file as
     * @param string|null $category Optional category/directory to store the file in
     * @return bool Whether the file was successfully stored
     */
    public function storeFile($file, string $filename, ?string $category = null): bool
    {
        $path = $this->resolvePath($category);
        
        // Handle different file input types
        if ($file instanceof UploadedFile) {
            return !is_null($file->storeAs($path, $filename, 'public'));
        } else {
            return Storage::disk('public')->put($path . '/' . $filename, $file);
        }
    }

    /**
     * Retrieve a file from local storage
     * 
     * @param string $filename The name of the file to retrieve
     * @param string|null $category Optional category/directory the file is stored in
     * @return string|null The file contents or null if not found
     */
    public function retrieveFile(string $filename, ?string $category = null): ?string
    {
        $path = $this->resolvePath($category);
        $fullPath = $path . '/' . $filename;
        
        if (Storage::disk('public')->exists($fullPath)) {
            return Storage::disk('public')->get($fullPath);
        }
        
        return null;
    }

    /**
     * Delete a file from local storage
     * 
     * @param string $filename The name of the file to delete
     * @param string|null $category Optional category/directory the file is stored in
     * @return bool Whether the file was successfully deleted
     */
    public function deleteFile(string $filename, ?string $category = null): bool
    {
        $path = $this->resolvePath($category);
        $fullPath = $path . '/' . $filename;
        
        if (Storage::disk('public')->exists($fullPath)) {
            return Storage::disk('public')->delete($fullPath);
        }
        
        // File does not exist
        return false;
    }
    
    /**
     * Get the public URL for a stored file
     * 
     * @param string $filename The name of the file
     * @param string|null $category Optional category/directory the file is stored in
     * @return string|null The public URL or null if file not found
     */
    public function getFileUrl(string $filename, ?string $category = null): ?string
    {
        $path = $this->resolvePath($category);
        $fullPath = $path . '/' . $filename;
        
        if (Storage::disk('public')->exists($fullPath)) {
            return Storage::url($fullPath);
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
     * Resolve the storage path based on category
     * 
     * @param string|null $category
     * @return string
     */
    protected function resolvePath(?string $category): string
    {
        if ($category) {
            // Ensure category is a valid directory name
            $category = preg_replace('/[^a-zA-Z0-9_-]/', '', $category);
            return 'storage/' . $category;
        }
        
        return 'storage';
    }
}
