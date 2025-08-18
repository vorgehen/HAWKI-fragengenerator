<?php

namespace App\Services\Chat\Room;

use App\Services\Chat\Room\Traits\RoomFunctions;
use App\Services\Chat\Room\Traits\RoomMembers;
use App\Services\Chat\Room\Traits\RoomMessages;


class RoomService{

    use RoomFunctions;
    use RoomMembers;
    use RoomMessages;

    public function __construct(

    ) {}
}
