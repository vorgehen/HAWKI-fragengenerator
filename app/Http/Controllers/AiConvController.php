<?php

namespace App\Http\Controllers;

use App\Models\AiConv;
use App\Models\AiConvMsg;
use App\Models\User;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use App\Services\StorageServices\StorageServiceFactory;
use App\Services\Message\MessageHandlerFactory;
use App\Services\Message\MessageContentValidator;
use App\Services\Attachment\AttachmentService;


use App\Services\Message\Handlers\PrivateMessageHandler;

class AiConvController extends Controller
{
    protected $messageHandler;
    protected $contentValidator;
    protected $attachmentService;

    public function __construct(){
        $this->messageHandler = MessageHandlerFactory::create('private');
        $this->attachmentService = new AttachmentService();
        $this->contentValidator = new MessageContentValidator();
    }

    /// RETURNS CONVERSATION DATA WHICH WILL BE DYNAMICALLY LOADED ON THE PAGE
    public function loadConv($slug)
    {
        $user = Auth::user();
        $conv = AiConv::where('slug', $slug)->where('user_id', $user->id)->firstOrFail();

        // Prepare the data to send back
        $data = [
            'id' => $conv->id,
            'name' => $conv->chat_name,
            'slug' => $conv->slug,
            'system_prompt'=> $conv->system_prompt,
            'messages' => $this->fetchConvMessages($conv)
        ];
        return response()->json($data);
    }



    ///CREATE NEW CONVERSATION
    public function createConv(Request $request)
    {
        $validatedData = $request->validate([
            'conv_name' => 'string|max:255',
            'system_prompt' => 'string'
        ]);

        if (!$request['conv_name']) {
            $validatedData['conv_name'] = 'New Chat';
        }

        $user = Auth::user();

        $conv = AiConv::create([
            'conv_name' => $validatedData['conv_name'],
            'user_id' => $user->id, // Associate the conversation with the user
            'slug' => Str::slug(Str::random(16)), // Create a unique slug
            'system_prompt'=> $validatedData['system_prompt'],
        ]);

        $response =[
            'success'=> true,
            'conv'=>$conv
        ];

        return response()->json($response, 201);
    }


    public function updateInfo(Request $request, $slug){
        $user = Auth::user();
        $conv = AiConv::where('slug', $slug)->firstOrFail();

        if ($conv->user_id != $user->id) {
            return response()->json([
                'success' => false,
                'response' => "GOTCHA: This chat doesn't belong to you",
            ]);
        }

        $validatedData = $request->validate([
            'system_prompt' => 'string'
        ]);

        $conv->update(['system_prompt' => $validatedData['system_prompt']]);

        return response()->json([
            'success' => true,
            'response' => "Info updated successfully",
        ]);
    }

    public function removeConv(Request $request, $slug){
        $user = Auth::user();
        $conv = AiConv::where('slug', $slug)->firstOrFail();

        // Check if the conv exists
        if (!$conv) {
            return response()->json(['success' => false, 'message' => 'Conv not found'], 404);
        }

        // Check if the user is an admin of the conv
        if ($conv->user_id != $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // Delete related messages and members
        $conv->messages()->delete();

        $conv->delete();

        return response()->json(['success' => true, 'message' => 'Conv deleted successfully']);
    }

    public function getUserConvs(Request $request)
    {
        // Assuming the user is authenticated
        $user = auth()->user();

        // Fetch all conversations related to the user
        $convs = $user->conversations()->with('messages')->get();

        return response()->json($convs);
    }


    /// get all messages in the conv
    /// 1. find conv in DB
    /// 2. create message array
    /// 3. return message array
    private function fetchConvMessages(AiConv $conv){

        $messages = $conv->messages;
        $messagesData = array();
        foreach ($messages as $message){
            $msgData = $this->messageHandler->createMessageObject($message);

            array_push($messagesData, $msgData);
        }
        return $messagesData;

    }


    /// 1. find the conv on DB
    /// 2. check the membership validation
    /// 3. assign an id to the message
    /// 4. create message object
    /// 5. qeue message for broadcasting
    /// 6. send response to the sender
    public function sendMessage(Request $request, $slug) {

        $validatedData = $request->validate([
            'isAi' => 'required|boolean',
            'threadID' => 'required|integer|min:0',
            'content' => 'required|array',
            'model' => 'string',
            'completion' => 'required|boolean',
        ]);

        //VALIDATE MESSAGE CONTENT
        try {
            $validatedData['content'] = $this->contentValidator->validate($validatedData['content']);
        } catch (ValidationException $e) {
            Log::error($e->getMessage());
        }

        // CREATE MESSAGE
        $result = $this->messageHandler->create($validatedData, $slug);

        return response()->json($result);
    }



    public function updateMessage(Request $request, $slug) {

        $validatedData = $request->validate([
            'message_id' => 'required|string',
            'content' => 'required|string|max:10000',
            'iv' => 'required|string',
            'tag' => 'required|string',
            'model' => 'nullable|string',
            'completion' => 'required|boolean',
        ]);

        $messageData = $this->messageHandler->update($validatedData, $slug);

        return response()->json([
            'success' => true,
            'messageData' => $messageData,
            'response' => "Message updated.",
        ]);

    }


    public function storeAttachment(Request $request) {
        $validateData = $request->validate([
            'file' => 'required|file|max:10240'
        ]);

        try {
            $result = $this->attachmentService->store($validateData['file'], 'private');
            return response()->json($result);
        }
        catch (ValidationException $e) {
            return back()
                ->withErrors($e->validator)
                ->withInput();
        }

    }
}
