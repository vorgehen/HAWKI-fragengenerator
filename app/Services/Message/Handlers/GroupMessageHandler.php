<?php


namespace App\Services\Message\Handlers;

use App\Models\AiConvMsg;
use App\Models\Room;
use App\Models\User;
use App\Models\Member;
use App\Models\Message;

use App\Services\Attachment\AttachmentService;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


class GroupMessageHandler extends BaseMessageHandler{


    public function create(array $data, string $slug): ?Message {

        $room = $data['room'];
        $member = $data['member'];

        $messageRole = 'user';
        $nextMessageId = $this->assignID($room, $data['threadID']);
        $message = Message::create([
            'room_id' => $room->id,
            'member_id' => $member->id,
            'user_id' => Auth::id(),
            'message_id' => $nextMessageId,
            'message_role' => $messageRole,
            'iv' => $data['content']['text']['iv'],
            'tag' => $data['content']['text']['tag'],
            'content' => $data['content']['text']['ciphertext'],
        ]);
        $message->addReadSignature($member);

        //ATTACHMENTS
        if(array_key_exists('attachments', $data['content'])){
            $attachments = $data['content']['attachments'];
            if($attachments){
                foreach($attachments as $attach){
                    $this->attachmentService->assignToMessage($message, $attach);
                }
            }
        }

        return $message;
    }


    public function update(array $data, string $slug): ?Message{

        $room = Room::where('slug', $slug)->firstOrFail();
        $member = $room->members()->where('user_id', Auth::id())->firstOrFail();

        if(!$room || !$member){
            return null;
        }

        $message = $room->messages->where('message_id', $data['message_id'])->first();

        $message->update([
            'content' => $data['content'],
            'iv' => $data['iv'],
            'tag' => $data['tag']
        ]);

        return $message;
    }


    public function markAsRead(string $message_id, string $slug): bool{

        try{
            $room = Room::where('slug', $slug)->firstOrFail();
            $member = $room->members()->where('user_id', Auth::id())->firstOrFail();
            $message = $room->messages->where('message_id', $message_id)->first();
            $message->addReadSignature($member);
            return true;
        }
        catch(\Exception $e){
            return false;
        }

    }



    public function delete(array $data, string $slug){




    }


    public function createMessageObject(AiConvMsg|Message $message): array
    {
        if(!$message instanceof Message){
            Log::error('AiConvMessage Sent to Group Handler');
        }

        $member = Member::find($message->member_id);
        $room = $message->room();

        $requestMember = $room->members()->where('user_id', Auth::id())->firstOrFail();

        $readStat = $message->isReadBy($requestMember);

        $msgData = [
            'member_id' => $member->id,
            'member_name' => $member->user->name,
            'message_role' => $message->message_role,
            'message_id' => $message->message_id,
            'read_status'=> $readStat,

            'author' => [
                'username' => $member->user->username,
                'name' => $member->user->name,
                'isRemoved' => $member->isRemoved,
                'avatar_url' => $member->user->avatar_id !== '' ? Storage::disk('public')->url('profile_avatars/' . $member->user->avatar_id) : null,
            ],
            'model' => $message->model,

            'content' => [
                'text' => [
                    'ciphertext'=> $message->content,
                    'iv' => $message->iv,
                    'tag' => $message->tag,
                ],
                'attachments' => $message->attachmentsAsArray(),
            ],

            'created_at' => $message->created_at->format('Y-m-d+H:i'),
            'updated_at' => $message->updated_at->format('Y-m-d+H:i'),
        ];

        return $msgData;
    }



}
