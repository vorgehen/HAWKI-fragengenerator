<?php

namespace App\Services\Profile;


use App\Models\PrivateUserData;
use App\Models\PasskeyBackup;

use App\Services\Chat\Room\RoomService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

use App\Services\Profile\Traits\PasskeyHandler;
use App\Services\Profile\Traits\ApiTokenHandler;

class ProfileService{

    use PasskeyHandler;
    use ApiTokenHandler;



    public function update($data): bool{
        $user = Auth::user();

        if(!empty($validatedData['img'])){
            // $imageController = new ImageController();
            // $response = $imageController->storeImage($validatedData['img'], 'profile_avatars');
            // $response = $response->original;

            // if ($response && $response['success']) {
            //     $user->update(['avatar_id' => $response['fileName']]);
            // } else {
            //     return false;
            // }
        }

        if(!empty($validatedData['displayName'])){
            $user->update(['name' => $data['displayName']]);
        }

        if(!empty($validatedData['bio'])){
            $user->update(['bio' => $data['bio']]);
        }

        return true;
    }


    public function resetProfile(){

        $user = Auth::user();
        $response = $this->deleteUserData();

        if($response === true){

            $userInfo = [
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'employeetype' => $user->employeetype,
            ];

            Auth::logout();

            Session::put('registration_access', true);
            Session::put('authenticatedUserInfo', json_encode($userInfo));

            return true;

        }
        else{
            return false;
        }
    }


    private function deleteUserData(): bool{

        $user = Auth::user();


        $roomService = new RoomService();
        $rooms = $user->rooms()->get();

        foreach($rooms as $room){
            $member = $room->members()->where('user_id', $user->id)->firstOrFail();
            if ($member) {
                $response = $roomService->removeMember($member, $room);
            }
        }

        $convs = $user->conversations()->get();

        foreach($convs as $conv){
            $conv->messages()->delete();
            $conv->delete();
        }

        $invitations = $user->invitations()->get();
        foreach($invitations as $inv){
            $inv->delete();
        }

        $prvUserData = PrivateUserData::where('user_id', $user->id)->get();
        foreach($prvUserData as $data){
            $data->delete();
        }

        $backups = PasskeyBackup::where('username', $user->username)->get();

        foreach($backups as $backup){
            $backup->delete();
        }

        $tokens = $user->tokens()->get();
        foreach($tokens as $token){
            $token->delete();
        }

        $user->revokProfile();

        return true;
    }

}
