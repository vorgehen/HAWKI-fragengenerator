<?php

namespace App\Services\StorageServices\Interfaces;

interface StorageServiceInterface
{
    /**
     * Store a file in storage
     * 
     * @param mixed $file The file to store (UploadedFile or file contents)
     * @param string $filename The name to save the file as
     * @param string|null $category Optional category to store the file in
     * @return bool Whether the file was successfully stored
     */
    public function storeFile($file, string $filename, ?string $category = null): bool;

    /**
     * Retrieve a file from storage
     * 
     * @param string $filename The name of the file to retrieve
     * @param string|null $category Optional category the file is stored in
     * @return string|null The file contents or null if not found
     */
    public function retrieveFile(string $filename, ?string $category = null): ?string;

    /**
     * Delete a file from storage
     * 
     * @param string $filename The name of the file to delete
     * @param string|null $category Optional category the file is stored in
     * @return bool Whether the file was successfully deleted
     */
    public function deleteFile(string $filename, ?string $category = null): bool;
    
    /**
     * Get the public URL for a stored file
     * 
     * @param string $filename The name of the file
     * @param string|null $category Optional category the file is stored in
     * @return string|null The public URL or null if file not found
     */
    public function getFileUrl(string $filename, ?string $category = null): ?string;
    
    /**
     * Generate a unique filename with the original extension
     * 
     * @param string $originalFilename The original filename
     * @return string A unique filename
     */
    public function generateUniqueFilename(string $originalFilename): string;
}