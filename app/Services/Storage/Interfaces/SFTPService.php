<?php

namespace App\Services\Storage\Interfaces;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Throwable;



//   - PHP SSH2 extension must be installed: apt-get install libssh2-1-dev php-ssh2

class SFTPService implements StorageServiceInterface
{
    protected string $host;
    protected int $port;
    protected string $username;
    protected string $password;
    protected string $basePath;
    protected $connection;
    protected $sftpConnection;

    public function __construct()
    {
        $this->host = config('filesystems.disks.sftp.host');
        $this->port = config('filesystems.disks.sftp.port', 22);
        $this->username = config('filesystems.disks.sftp.username');
        $this->password = config('filesystems.disks.sftp.password');
        $this->basePath = config('filesystems.disks.sftp.base_path', '/');
    }

    /**
     * Store a file in SFTP storage
     *
     * @param UploadedFile|string $file The file to store (either UploadedFile instance or file contents)
     * @param string $filename The name to save the file as
     * @param string $uuid The unique identifier for the file
     * @param string $category The category to store the file in
     * @return bool Whether the file was successfully stored
     */
    public function storeFile(UploadedFile|string $file, string $filename, string $uuid, string $category): bool
    {
        try {
            if (!$this->connect()) {
                return false;
            }

            $remotePath = $this->buildPath($category, $uuid, $filename);
            $fullPath = $this->getFullPath($remotePath);

            // Create directory structure if it doesn't exist
            $this->createDirectoryStructure($category, $uuid);

            $contents = ($file instanceof UploadedFile)
                ? file_get_contents($file->getRealPath())
                : $file;

            // Create a temporary file for the content
            $tempFile = tempnam(sys_get_temp_dir(), 'sftp_upload_');
            file_put_contents($tempFile, $contents);

            $result = ssh2_scp_send($this->connection, $tempFile, $fullPath);

            // Clean up temporary file
            unlink($tempFile);

            if (!$result) {
                Log::error("Failed to store file [$fullPath] via SFTP");
                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::error("SFTP upload error: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Retrieve the first file from the specified folder
     */
    public function retrieveFile(string $uuid, string $category)
    {
        $category = $category ?? 'default';

        try {
            if (!$this->connect()) {
                return false;
            }

            $folder = $this->buildFolder($category, $uuid);
            $fullFolderPath = $this->getFullPath($folder);

            // List files in the directory
            $files = $this->listDirectoryFiles($fullFolderPath);

            if (empty($files)) {
                return false;
            }

            // Get the first file
            $firstFile = $files[0];
            $fullFilePath = $fullFolderPath . '/' . $firstFile;

            // Create a temporary file to receive the content
            $tempFile = tempnam(sys_get_temp_dir(), 'sftp_download_');

            $result = ssh2_scp_recv($this->connection, $fullFilePath, $tempFile);

            if (!$result) {
                unlink($tempFile);
                return false;
            }

            $content = file_get_contents($tempFile);
            unlink($tempFile);

            return $content;
        } catch (Throwable $e) {
            Log::error("SFTP retrieve error: " . $e->getMessage(), ['exception' => $e]);
            return false;
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Delete the entire folder and all its contents
     */
    public function deleteFile(string $uuid, string $category): bool
    {
        try {
            if (!$this->connect()) {
                return false;
            }

            $folder = $this->buildFolder($category, $uuid);
            $fullFolderPath = $this->getFullPath($folder);

            return $this->deleteDirectoryRecursive($fullFolderPath);
        } catch (Throwable $e) {
            Log::error("SFTP delete error: " . $e->getMessage(), ['exception' => $e]);
            return false;
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Get a download URL for the first file in the folder (returns path since SFTP doesn't have public URLs)
     */
    public function getFileUrl(string $uuid, string $category): ?string
    {
        $category = $category ?? 'default';

        try {
            if (!$this->connect()) {
                return null;
            }

            $folder = $this->buildFolder($category, $uuid);
            $fullFolderPath = $this->getFullPath($folder);

            $files = $this->listDirectoryFiles($fullFolderPath);

            if (empty($files)) {
                return null;
            }

            // Return the full path to the first file (SFTP doesn't have public URLs)
            return $fullFolderPath . '/' . $files[0];
        } catch (Throwable $e) {
            Log::error("SFTP getFileUrl error: " . $e->getMessage(), ['exception' => $e]);
            return null;
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Delete the "output" folder and all its contents for a specific file (UUID/category).
     */
    public function deleteConvertedFiles(string $uuid, ?string $category = null): bool
    {
        $category = $category ?? 'default';

        try {
            if (!$this->connect()) {
                return false;
            }

            $outputFolder = $this->buildFolder($category, $uuid) . '/output';
            $fullOutputPath = $this->getFullPath($outputFolder);

            return $this->deleteDirectoryRecursive($fullOutputPath);
        } catch (Throwable $e) {
            Log::error("Failed to delete output folder: " . $e->getMessage(), ['outputFolder' => $outputFolder ?? 'unknown']);
            throw $e;
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Get paths for all output files of a given type (SFTP doesn't have public URLs)
     */
    public function getOutputFilesUrls(string $uuid, ?string $category = null, string $fileType): array
    {
        $category = $category ?? 'default';

        try {
            if (!$this->connect()) {
                return [];
            }

            $outputFolder = $this->buildFolder($category, $uuid) . '/output';
            $fullOutputPath = $this->getFullPath($outputFolder);

            $files = $this->listDirectoryFiles($fullOutputPath);
            $urls = [];

            foreach ($files as $file) {
                if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === strtolower($fileType)) {
                    $urls[] = $fullOutputPath . '/' . $file;
                }
            }

            return $urls;
        } catch (Throwable $e) {
            Log::error("SFTP getOutputFilesUrls error: " . $e->getMessage(), ['exception' => $e]);
            return [];
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Retrieve all output files with the specified extension
     */
    public function retrieveOutputFilesByType(string $uuid, string $category, string $fileType): array
    {
        $category = $category ?? 'default';

        try {
            if (!$this->connect()) {
                return [];
            }

            $outputFolder = $this->buildFolder($category, $uuid) . '/output';
            $fullOutputPath = $this->getFullPath($outputFolder);

            $files = $this->listDirectoryFiles($fullOutputPath);
            $matches = [];

            foreach ($files as $file) {
                if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === strtolower($fileType)) {
                    $fullFilePath = $fullOutputPath . '/' . $file;

                    // Download file content
                    $tempFile = tempnam(sys_get_temp_dir(), 'sftp_retrieve_');
                    $result = ssh2_scp_recv($this->connection, $fullFilePath, $tempFile);

                    if ($result) {
                        $matches[] = [
                            'path' => $fullFilePath,
                            'contents' => file_get_contents($tempFile),
                        ];
                    }

                    unlink($tempFile);
                }
            }

            return $matches;
        } catch (Throwable $e) {
            Log::error("SFTP retrieveOutputFilesByType error: " . $e->getMessage(), ['exception' => $e]);
            return [];
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Connect to SFTP server
     */
    private function connect(): bool
    {
        if ($this->connection && $this->sftpConnection) {
            return true;
        }

        try {
            $this->connection = ssh2_connect($this->host, $this->port);

            if (!$this->connection) {
                Log::error("Failed to connect to SFTP server: {$this->host}:{$this->port}");
                return false;
            }

            $auth = ssh2_auth_password($this->connection, $this->username, $this->password);

            if (!$auth) {
                Log::error("SFTP authentication failed for user: {$this->username}");
                return false;
            }

            $this->sftpConnection = ssh2_sftp($this->connection);

            if (!$this->sftpConnection) {
                Log::error("Failed to initialize SFTP subsystem");
                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::error("SFTP connection error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Disconnect from SFTP server
     */
    private function disconnect(): void
    {
        if ($this->connection) {
            // SSH2 connections are automatically closed when the resource is destroyed
            $this->connection = null;
            $this->sftpConnection = null;
        }
    }

    /**
     * Create directory structure in SFTP
     */
    private function createDirectoryStructure(string $category, string $uuid): void
    {
        $folders = [
            $category,
            $this->buildFolder($category, $uuid)
        ];

        foreach ($folders as $folder) {
            $fullPath = $this->getFullPath($folder);
            ssh2_sftp_mkdir($this->sftpConnection, $fullPath, 0755, true);
        }
    }

    /**
     * List files in a directory
     */
    private function listDirectoryFiles(string $path): array
    {
        $files = [];

        try {
            $handle = opendir("ssh2.sftp://{$this->sftpConnection}{$path}");

            if (!$handle) {
                return $files;
            }

            while (($file = readdir($handle)) !== false) {
                if ($file !== '.' && $file !== '..') {
                    $fullFilePath = $path . '/' . $file;
                    $stat = ssh2_sftp_stat($this->sftpConnection, $fullFilePath);

                    // Only include files, not directories
                    if ($stat && !($stat['mode'] & 0x4000)) {
                        $files[] = $file;
                    }
                }
            }

            closedir($handle);
        } catch (Throwable $e) {
            Log::error("Error listing SFTP directory: " . $e->getMessage(), ['path' => $path]);
        }

        return $files;
    }

    /**
     * Delete directory and all contents recursively
     */
    private function deleteDirectoryRecursive(string $path): bool
    {
        try {
            $handle = opendir("ssh2.sftp://{$this->sftpConnection}{$path}");

            if (!$handle) {
                return true; // Directory doesn't exist, consider it deleted
            }

            while (($file = readdir($handle)) !== false) {
                if ($file !== '.' && $file !== '..') {
                    $fullPath = $path . '/' . $file;
                    $stat = ssh2_sftp_stat($this->sftpConnection, $fullPath);

                    if ($stat['mode'] & 0x4000) {
                        // It's a directory, recurse
                        $this->deleteDirectoryRecursive($fullPath);
                    } else {
                        // It's a file, delete it
                        ssh2_sftp_unlink($this->sftpConnection, $fullPath);
                    }
                }
            }

            closedir($handle);

            // Delete the directory itself
            return ssh2_sftp_rmdir($this->sftpConnection, $path);
        } catch (Throwable $e) {
            Log::error("Error deleting SFTP directory: " . $e->getMessage(), ['path' => $path]);
            return false;
        }
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
     * Get the full path including base path
     */
    private function getFullPath(string $path): string
    {
        return rtrim($this->basePath, '/') . '/' . trim($path, '/');
    }
}
