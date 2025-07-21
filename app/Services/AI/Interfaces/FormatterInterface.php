<?php

namespace App\Services\AI\Interfaces;

interface FormatterInterface
{
    public function format(array $payload): array;


    public function formatMessageContent(array $content, array $attachmentsMap): array;


}
