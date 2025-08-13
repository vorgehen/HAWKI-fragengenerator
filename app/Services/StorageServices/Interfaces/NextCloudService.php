<?php

namespace App\Services\StorageServices\Interfaces;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Throwable;

class NextCloudService implements StorageServiceInterface
{
    protected string $baseUrl;
    protected string $username;
    protected string $password;
    protected string $basePath;

    public function __construct()
    {
        $this->baseUrl = config('filesystems.disks.nextcloud.base_url');
        $this->username = config('filesystems.disks.nextcloud.username');
        $this->password = config('filesystems.disks.nextcloud.password');
        $this->basePath = config('filesystems.disks.nextcloud.base_path', '/');
    }

    /**
     * Store a file in NextCloud storage
     *
     * @param UploadedFile|string $file The file to store (either UploadedFile instance or file contents)
     * @param string $filename The name to save the file as
     * @param string $uuid The unique identifier for the file
     * @param string $category The category to store the file in
     * @return bool Whether the file was successfully stored
     */
    public function storeFile(UploadedFile|string $file, string $filename, string $uuid, string $category): bool
    {
        $path = $this->buildPath($category, $uuid, $filename);
        $webdavUrl = $this->getWebDAVUrl($path);

        try {
            $contents = ($file instanceof UploadedFile)
                ? file_get_contents($file->getRealPath())
                : $file;

            // Create directory structure if it doesn't exist
            $this->createDirectoryStructure($category, $uuid);

            $response = Http::withBasicAuth($this->username, $this->password)
                ->withBody($contents)
                ->put($webdavUrl);

            if (!$response->successful()) {
                Log::error("Failed to store file [$path] in NextCloud", [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::error("NextCloud upload error: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * Retrieve the first file from the specified folder
     */
    public function retrieveFile(string $uuid, string $category)
    {
        $category = $category ?? 'default';
        $folder = $this->buildFolder($category, $uuid);
        $webdavUrl = $this->getWebDAVUrl($folder);

        try {
            // List files in the directory
            $response = Http::withBasicAuth($this->username, $this->password)
                ->withHeaders(['Depth' => '1'])
                ->send('PROPFIND', $webdavUrl);

            if (!$response->successful()) {
                Log::error("PROPFIND failed for folder: $webdavUrl", [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }

            // Parse the WebDAV response to get file list
            $fullPath = trim($this->basePath, '/') . '/' . trim($folder, '/');
            $files = $this->parseWebDAVResponse($response->body(), $fullPath);

            if (empty($files)) {
                Log::error("No files found in folder: $folder", [
                    'webdav_url' => $webdavUrl,
                    'full_path' => $fullPath,
                    'base_path' => $this->basePath,
                    'response_body' => $response->body()
                ]);
                return false;
            }

            // Get the first file
            $firstFile = $files[0];
            $fileUrl = $this->getWebDAVUrl($firstFile);

            // //Log::debug("Attempting to download file", [
            //     'first_file' => $firstFile,
            //     'file_url' => $fileUrl
            // ]);

            $fileResponse = Http::withBasicAuth($this->username, $this->password)
                ->get($fileUrl);

            // //Log::debug("File download response", [
            //     'status' => $fileResponse->status(),
            //     'successful' => $fileResponse->successful(),
            //     'body_length' => strlen($fileResponse->body())
            // ]);

            if ($fileResponse->successful()) {
                return $fileResponse->body();
            }

            // Log::error("File download failed", [
            //     'status' => $fileResponse->status(),
            //     'response' => $fileResponse->body()
            // ]);

            return false;
        } catch (Throwable $e) {
            Log::error("NextCloud retrieve error: " . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }

    /**
     * Delete the entire folder and all its contents
     */
    public function deleteFile(string $uuid, string $category): bool
    {
        $folder = $this->buildFolder($category, $uuid);
        $webdavUrl = $this->getWebDAVUrl($folder);

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->delete($webdavUrl);

            return $response->successful();
        } catch (Throwable $e) {
            Log::error("NextCloud delete error: " . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }

    /**
     * Get a temporary download URL for the first file in the folder
     */
    public function getFileUrl(string $uuid, string $category): ?string
    {
        $category = $category ?? 'default';
        $folder = $this->buildFolder($category, $uuid);
        $webdavUrl = $this->getWebDAVUrl($folder);

        try {
            // List files in the directory
            $response = Http::withBasicAuth($this->username, $this->password)
                ->withHeaders(['Depth' => '1'])
                ->send('PROPFIND', $webdavUrl);

            if (!$response->successful()) {
                return null;
            }

            // Parse the WebDAV response to get file list
            $fullPath = trim($this->basePath, '/') . '/' . trim($folder, '/');
            $files = $this->parseWebDAVResponse($response->body(), $fullPath);

            if (empty($files)) {
                return null;
            }

            // Return direct download URL for the first file
            $firstFile = $files[0];
            return $this->getWebDAVUrl($firstFile);
        } catch (Throwable $e) {
            Log::error("NextCloud getFileUrl error: " . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }

    /**
     * Delete the "output" folder and all its contents for a specific file (UUID/category).
     */
    public function deleteConvertedFiles(string $uuid, ?string $category = null): bool
    {
        $category = $category ?? 'default';
        $outputFolder = $this->buildFolder($category, $uuid) . '/output';
        $webdavUrl = $this->getWebDAVUrl($outputFolder);

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->delete($webdavUrl);

            if (!$response->successful() && $response->status() !== 404) {
                Log::warning("Failed to delete output folder [$outputFolder] in NextCloud", [
                    'status' => $response->status()
                ]);
                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::error("Failed to delete output folder: " . $e->getMessage(), ['outputFolder' => $outputFolder]);
            throw $e;
        }
    }

    /**
     * Get URLs for all output files of a given type
     */
    public function getOutputFilesUrls(string $uuid, ?string $category = null, string $fileType): array
    {
        $category = $category ?? 'default';
        $outputFolder = $this->buildFolder($category, $uuid) . '/output';
        $webdavUrl = $this->getWebDAVUrl($outputFolder);

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->withHeaders(['Depth' => '1'])
                ->send('PROPFIND', $webdavUrl);

            if (!$response->successful()) {
                return [];
            }

            $fullOutputPath = trim($this->basePath, '/') . '/' . trim($outputFolder, '/');
            $files = $this->parseWebDAVResponse($response->body(), $fullOutputPath);
            $urls = [];

            foreach ($files as $file) {
                if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === strtolower($fileType)) {
                    $urls[] = $this->getWebDAVUrl($file);
                }
            }

            return $urls;
        } catch (Throwable $e) {
            Log::error("NextCloud getOutputFilesUrls error: " . $e->getMessage(), ['exception' => $e]);
            return [];
        }
    }

    /**
     * Retrieve all output files with the specified extension
     */
    public function retrieveOutputFilesByType(string $uuid, string $category, string $fileType): array
    {
        $category = $category ?? 'default';
        $outputFolder = $this->buildFolder($category, $uuid) . '/output';
        $webdavUrl = $this->getWebDAVUrl($outputFolder);

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->withHeaders(['Depth' => '1'])
                ->send('PROPFIND', $webdavUrl);

            if (!$response->successful()) {
                return [];
            }

            $fullOutputPath = trim($this->basePath, '/') . '/' . trim($outputFolder, '/');
            $files = $this->parseWebDAVResponse($response->body(), $fullOutputPath);
            $matches = [];

            foreach ($files as $file) {
                if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === strtolower($fileType)) {
                    $fileUrl = $this->getWebDAVUrl($file);
                    $fileResponse = Http::withBasicAuth($this->username, $this->password)
                        ->get($fileUrl);

                    if ($fileResponse->successful()) {
                        $matches[] = [
                            'path' => $file,
                            'contents' => $fileResponse->body(),
                        ];
                    }
                }
            }

            return $matches;
        } catch (Throwable $e) {
            Log::error("NextCloud retrieveOutputFilesByType error: " . $e->getMessage(), ['exception' => $e]);
            return [];
        }
    }

    /**
     * Create directory structure in NextCloud
     */
    private function createDirectoryStructure(string $category, string $uuid): void
    {
        $folders = [
            $category,
            $this->buildFolder($category, $uuid)
        ];

        foreach ($folders as $folder) {
            $webdavUrl = $this->getWebDAVUrl($folder);
            Http::withBasicAuth($this->username, $this->password)
                ->send('MKCOL', $webdavUrl);
        }
    }

    /**
     * Parse WebDAV PROPFIND response to extract file paths
     */
    private function parseWebDAVResponse(string $xmlResponse, string $basePath): array
    {
        $files = [];

        try {
            $xml = simplexml_load_string($xmlResponse);
            if ($xml === false) {
                Log::error("Failed to parse WebDAV XML response");
                return $files;
            }

            $xml->registerXPathNamespace('d', 'DAV:');
            $responses = $xml->xpath('//d:response');

            foreach ($responses as $response) {
                $hrefNodes = $response->xpath('d:href');
                if (empty($hrefNodes)) {
                    continue;
                }

                $href = (string) $hrefNodes[0];
                $decodedHref = urldecode($href);

                // Log for debugging
                // //Log::debug("WebDAV href: " . $href . " -> " . $decodedHref);

                // Remove WebDAV prefix and get relative path
                $webdavPrefix = '/remote.php/dav/files/' . $this->username . '/';
                if (strpos($decodedHref, $webdavPrefix) === 0) {
                    $relativePath = substr($decodedHref, strlen($webdavPrefix));
                } else {
                    // Try with just the username prefix
                    $simplePrefix = '/' . $this->username . '/';
                    if (strpos($decodedHref, $simplePrefix) !== false) {
                        $relativePath = substr($decodedHref, strpos($decodedHref, $simplePrefix) + strlen($simplePrefix));
                    } else {
                        //Log::debug("Could not parse href: " . $decodedHref);
                        continue;
                    }
                }

                $relativePath = trim($relativePath, '/');

                // //Log::debug("Processing WebDAV item", [
                //     'href' => $href,
                //     'decoded_href' => $decodedHref,
                //     'relative_path' => $relativePath,
                //     'base_path' => $basePath
                // ]);

                // Check if this is a file (not a directory) and within our base path
                if (!empty($relativePath) && $relativePath !== $basePath) {
                    // Check if it's within the base path folder
                    if (strpos($relativePath, $basePath . '/') === 0) {
                        // Check if it's a direct file (not in a subdirectory like output/)
                        $pathAfterBase = substr($relativePath, strlen($basePath) + 1);
                        //Log::debug("Path after base", [
                        //     'path_after_base' => $pathAfterBase,
                        //     'has_subdirectory' => strpos($pathAfterBase, '/') !== false
                        // ]);

                        if (!empty($pathAfterBase) && strpos($pathAfterBase, '/') === false) {
                            $files[] = $relativePath;
                            //Log::debug("Added file: " . $relativePath);
                        } else {
                            //Log::debug("Skipped (subdirectory or empty): " . $relativePath);
                        }
                    } else {
                        //Log::debug("Skipped (not in base path): " . $relativePath);
                    }
                } else {
                    //Log::debug("Skipped (empty or is base path): " . $relativePath);
                }
            }

            //Log::debug("Total files found: " . count($files), $files);
        } catch (Throwable $e) {
            Log::error("Error parsing WebDAV response: " . $e->getMessage(), [
                'xml_snippet' => substr($xmlResponse, 0, 500)
            ]);
        }

        return $files;
    }

    /**
     * Build the folder path for a given category/uuid
     */
    private function buildFolder(string $category, string $uuid): string
    {
        return trim($category, '/') . '/' . trim($uuid, '/');
    }

    /**
     * Build the full file path for storage
     */
    protected function buildPath(string $category, string $uuid, string $name): string
    {
        return trim($category, '/') . '/' . trim($uuid, '/') . '/' . trim($name, '/');
    }

    /**
     * Get the WebDAV URL for a given path
     */
    private function getWebDAVUrl(string $path): string
    {
        // Check if the path already starts with the base path
        $basePath = trim($this->basePath, '/');
        $trimmedPath = trim($path, '/');

        if (strpos($trimmedPath, $basePath . '/') === 0) {
            // Path already includes base path, use as-is
            $cleanPath = $trimmedPath;
        } else {
            // Path doesn't include base path, prepend it
            $cleanPath = $basePath . '/' . $trimmedPath;
        }

        return rtrim($this->baseUrl, '/') . '/remote.php/dav/files/' . $this->username . '/' . $cleanPath;
    }
}
