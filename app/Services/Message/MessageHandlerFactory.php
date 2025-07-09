<?php

namespace App\Services\Message;

use App\Services\Message\Interfaces\MessageInterface;
use App\Services\Message\Handlers\PrivateMessageHandler;
use App\Services\Message\Handlers\GroupMessageHandler;

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
