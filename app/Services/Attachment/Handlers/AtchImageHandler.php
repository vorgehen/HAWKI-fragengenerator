<?php


namespace App\Services\Attachment\Handlers;

use App\Services\Attachment\Interfaces\AttachmentInterface;

use App\Models\AiConvMsg;
use App\Models\Message;

use App\Services\StorageServices\StorageServiceFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;


class AtchImageHandler implements AttachmentInterface
{
    protected $storageService;
    public function __construct(){
        $this->storageService = StorageServiceFactory::create();
    }


    public function store($file, string $category): array{
        $uuid = Str::uuid();
        $originalName = $file->getClientOriginalName();

        $stored = $this->storageService->storeFile($file,$originalName, $uuid, $category);

        if (!$stored) {
            throw new \Exception('Failed to store file.');
        }

        return [
            'succes' => true,
            'uuid' => $uuid
        ];
    }
}
