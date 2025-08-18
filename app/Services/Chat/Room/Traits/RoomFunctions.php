<?php

namespace App\Services\Chat\Room\Traits;

use App\Models\Room;
use App\Models\Member;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

trait RoomFunctions{

    public function create(array $data): Room
    {
        // Create the room with name and description
        $room = Room::create([
            'room_name' => $data['room_name'],
        ]);
        // Add AI as assistant
        $room->addMember(1, Member::ROLE_ASSISTANT);
        // Add the creator as admin
        $room->addMember(Auth::id(), Member::ROLE_ADMIN);

        return $room;
    }

    public function load($slug)
    {
        $room = Room::where('slug', $slug)->firstOrFail();

        if(!$room->isMember(Auth::id())){
            throw new AuthorizationException();
        }

        $roomIcon = ($room->room_icon !== '' && $room->room_icon !== null)
        ? Storage::disk('public')->url('room_avatars/' . $room->room_icon)
        : null;


        $membership = $room->members()->where('user_id', Auth::id())->first();
        $membership->updateLastRead();

        $role = $membership->role;

        $data = [
            'id' => $room->id,
            'name' => $room->room_name,
            'room_icon' => $roomIcon,
            'slug' => $room->slug,
            'system_prompt' => $room->system_prompt,
            'room_description' => $room->room_description,
            'role' => $role,

            'members' => $room->members->map(function ($member) {
                return [
                    'user_id' => $member->user->id,
                    'name' => $member->user->name,
                    'username' => $member->user->username,
                    'role' => $member->role,
                    'employeetype' => $member->user->employeetype,
                    'avatar_url' => $member->user->avatar_id !== '' ? Storage::disk('public')->url('profile_avatars/' . $member->user->avatar_id) : null,
                ];
            }),

            'messagesData' => $room->messageObjects()
        ];

        return $data;
    }

    public function update(array $data, string $slug){

        $user = Auth::user();
        $room = Room::where('slug', $slug)->firstOrFail();

        try{
            if(!empty($data['img'])){
                // $imageController = new ImageController();
                // $response = $imageController->storeImage($data['img'], 'room_avatars');
                // $response = $response->original;

                // if ($response && $response['success']) {
                //     $room->update(['room_icon' => $response['fileName']]);
                // } else {
                //     return false;
                // }
            }

            if(!empty($data['system_prompt'])){
                $room->update(['system_prompt' => $data['system_prompt']]);
            }
            if(!empty($data['description'])){
                $room->update(['room_description' => $data['description']]);
            }
            if(!empty($data['name'])){
                $room->update(['room_name' => $data['name']]);
            }
            return true;
        }
        catch(Exception $e){
            Log::error("Failed to update Room Information. Error: $e");
            return false;
        }
    }


    public function delete($slug){
        $room = Room::where('slug', $slug)->firstOrFail();

        if(!$room->isMember(Auth::id())){
            throw new AuthorizationException();
        }

        try{
            $room->deleteRoom();
            return true;
        }
        catch(Exception $e){
            Log::error("Failed to remove Room Information. Error: $e");
            return false;
        }
    }

}
