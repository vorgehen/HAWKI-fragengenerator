<?php

namespace App\Services\Attachment;

use App\Services\Attachment\Interfaces\AttachmentInterface;
use App\Services\Attachment\Handlers\AtchDocumentHandler;
use App\Services\Attachment\Handlers\AtchImageHandler;

use App\Services\Attachment\InvalidArgumentException;


class AttachmentFactory
{
    /**
     * Create a storage service based on configuration
     *
     * @param string|null $type Optional storage type, defaults to configured default
     * @return AttachmentInterface
     */
    public static function create(string $type): ?AttachmentInterface
    {
        return match ($type) {
            'image'    => new AtchImageHandler(),
            'document' => new AtchDocumentHandler(),
            default    => throw new \InvalidArgumentException("Unknown attachment type: $type"),
        };
    }
}
