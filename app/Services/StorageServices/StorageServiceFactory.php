<?php

namespace App\Services\StorageServices;

use App\Services\StorageServices\Interfaces\StorageServiceInterface;
use App\Services\StorageServices\Interfaces\LocalStorageService;
use App\Services\StorageServices\Interfaces\S3Service;
use App\Services\StorageServices\Interfaces\NextCloudService;

class StorageServiceFactory
{
    /**
     * Create a storage service based on configuration
     *
     * @param string|null $type Optional storage type, defaults to configured default
     * @return StorageServiceInterface
     */
    public static function create(?string $type = null): StorageServiceInterface
    {
        $storageType = $type ?? config('filesystems.storage_service', 'local');

        switch ($storageType) {
            case 's3':
                return new S3Service();
            case 'nextcloud':
                return new NextCloudService();
            case 'local':
            default:
                return new LocalStorageService();
        }
    }
}
