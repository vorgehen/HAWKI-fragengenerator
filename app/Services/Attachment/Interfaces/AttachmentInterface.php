<?php

namespace App\Services\Attachment\Interfaces;


interface AttachmentInterface
{
    public function store($file, string $category): array;
}
