<?php

namespace App\Services\Chat\Message;

use App\Services\Chat\Message\Interfaces\MessageInterface;
use App\Services\Chat\Message\Handlers\PrivateMessageHandler;
use App\Services\Chat\Message\Handlers\GroupMessageHandler;

class MessageHandlerFactory{

    public static function create(string $type): ?MessageInterface
    {
        switch ($type) {
            case 'private':
                return new PrivateMessageHandler();
            case 'group':
                return new GroupMessageHandler();
            default:
                return null;
        }
    }

}
