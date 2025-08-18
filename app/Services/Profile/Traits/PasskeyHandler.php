<?php

namespace App\Services\Profile\Traits;


use App\Models\PasskeyBackup;
use App\Models\PrivateUserData;
use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\Session;


trait PasskeyHandler
{

    public function backupPassKey(array $data){
        $userInfo = json_decode(Session::get('authenticatedUserInfo'), true);
        $username = $userInfo['username'];

        if($username != $data['username']){
            return response()->json([
                'success' => false,
                'message' => 'Username comparision failed!',
            ]);
        }

        PasskeyBackup::updateOrCreate([
            'username' => Auth::user()->username,
            'ciphertext' => $data['cipherText'],
            'iv' => $data['iv'],
            'tag' => $data['tag'],
        ]);
    }


    public function retrievePasskeyBackup(): array{

        $user = Auth::user();
        $backup = PasskeyBackup::where('username', $user->username)->firstOrFail();

        return [
            'ciphertext' => $backup->ciphertext,
            'iv' => $backup->iv,
            'tag' => $backup->tag,
        ];
    }

}
