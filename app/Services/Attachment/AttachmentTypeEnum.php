<?php
namespace App\Enums;

enum AttachmentTypeEnum: string
{
    case IMAGE = 'image';
    case AUDIO = 'audio';
    case DOCUMENT = 'document';
    case VIDEO = 'video';
    case OTHER = 'other';
}
