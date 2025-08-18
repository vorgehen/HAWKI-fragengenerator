<?php

namespace App\Services\Storage;

use App\Services\Storage\Interfaces\StorageServiceInterface;
use App\Services\Storage\Interfaces\LocalStorageService;
use App\Services\Storage\Interfaces\S3Service;
use App\Services\Storage\Interfaces\NextCloudService;
use App\Services\Storage\Interfaces\SFTPService;

class StorageServiceFactory
{
    /**
     * Create a storage service based on configuration
     *
     * @param string|null $type Optional storage type, defaults to configured default
     * @return StorageServiceInterface
     */
    public static function create(?string $type = 'default'): StorageServiceInterface
    {
        $storageType = config("filesystems.$type");

        switch ($storageType) {
            case 's3':
                return new S3Service();
            case 'nextcloud':
                return new NextCloudService();
            case 'sftp':
                return new SFTPService();
            case 'local':
                return new LocalStorageService('local');
            case 'public':
                return new LocalStorageService('public');
            case 'data_repo':
            default:
                return new LocalStorageService('data_repo');
        }
    }
}
