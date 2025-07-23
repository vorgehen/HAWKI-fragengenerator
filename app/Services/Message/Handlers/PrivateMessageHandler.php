<?php


namespace App\Services\Message\Handlers;

use App\Models\User;
use App\Models\AiConv;
use App\Models\AiConvMsg;

use App\Services\Attachment\AttachmentService;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use Illuminate\Http\Request;


class PrivateMessageHandler extends BaseMessageHandler{

    protected $attachmentService;
    public function __construct(){
        $this->attachmentService = new AttachmentService();
    }

    public function create(array $data, string $slug): array {

        $conv = AiConv::where('slug', $slug)->firstOrFail();
        if ($conv->user_id !== Auth::id()) {
            return response()->json([
                'success'=> false,
                'error' => 'Access denied'
            ], 403);
        }

        $user = $data['isAi'] ? User::find(1) : Auth::user();
        $messageRole = $data['isAi'] ? 'assistant' : 'user';

        $nextMessageId = $this->assignID($conv, $data['threadID']);
        $message = AiConvMsg::create([
            'conv_id' => $conv->id,
            'user_id' => $user->id,
            'model' => $data['isAi'] ? $data['model'] : null,

            'message_role' => $messageRole,
            'message_id' => $nextMessageId,
            'iv' => $data['content']['text']['iv'],
            'tag' => $data['content']['text']['tag'],
            'content' => $data['content']['text']['ciphertext'],
            'completion' => $data['completion'],
        ]);

        //ATTACHMENTS
        if(array_key_exists('attachments', $data['content'])){
            $attachments = $data['content']['attachments'];
            if($attachments){
                foreach($attachments as $attach){
                    $this->attachmentService->assignToMessage($message, $attach);
                }
            }
        }

        $messageData = $this->createMessageObject($message);
        return [
            'success'=> true,
            'messageData'=> $messageData
        ];
    }



    public function update(array $data, string $slug){

        $conv = AiConv::where('slug', $slug)->firstOrFail();
        if ($conv->user_id !== Auth::id()) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        //find the target message
        $message = $conv->messages->where('message_id', $data['message_id'])->first();

        $message->update([
            'content' => $data['content'],
            'iv' => $data['iv'],
            'tag' => $data['tag'],
            'model' => $data['model'],
            'completion' => $data['completion']
        ]);

        $messageData = $message->toArray();
        $messageData['created_at'] = $message->created_at->format('Y-m-d+H:i');
        $messageData['updated_at'] = $message->updated_at->format('Y-m-d+H:i');

        return $messageData;

    }


    public function delete(array $data, string $slug){

        $conv = AiConv::where('slug', $slug)->firstOrFail();
        if ($conv->user_id !== Auth::id()) {
            return response()->json([
                'success'=> false,
                'error' => 'Access denied'
            ], 403);
        }

        $message = $conv->messages->where('message_id', $data['message_id'])->first();
        $message->delete();

    }



    public function createMessageObject($message): array
    {
        //if AI is the author, then username and name are the same.
        //if User has created the message then fetch the name from model.
        $user =  $message->user;
        $msgData = [
            'message_role' => $message->message_role,
            'message_id' => $message->message_id,
            'author' => [
                'username' => $user->username,
                'name' => $user->name,
                'avatar_url' => $user->avatar_id !== '' ? Storage::disk('public')->url('profile_avatars/' . $user->avatar_id) : null,
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


            'completion' => $message->completion,
            'created_at' => $message->created_at->format('Y-m-d+H:i'),
            'updated_at' => $message->updated_at->format('Y-m-d+H:i'),
        ];

        return $msgData;
    }



}
