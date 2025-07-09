<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\StorageServices\StorageServiceFactory;

class Message extends Model
{
    // NOTE: CONTENT = RAWCONTENT

    protected $fillable = [
        'room_id',
        'message_id',
        'message_role',
        'member_id',
        'model',
        'iv',
        'tag',
        'content',
        'reader_signs'
    ];



    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function user()
    {
        return $this->member->user();
    }

    public function isReadBy($member)
    {
        $hay = json_decode($this->reader_signs, true) ?? [];
        return in_array($member->id, $hay);
    }

    public function addReadSignature($member)
    {
        if (!$this->isReadBy($member)) {
            $signs = json_decode($this->reader_signs, true) ?? [];
            $signs[] = $member->id;
            $this->reader_signs = json_encode($signs);
            $this->save();
        }
    }



    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }


    public function attachmentsAsArray()
    {
        $attachments = $this->attachments;

        if ($attachments->isEmpty()) {
            return null;
        }
        $storageService = StorageServiceFactory::create();

        return $attachments->map(function ($attach) use ($storageService) {
            return [
                'fileData' => [
                    'uuid'     => $attach->uuid,
                    'name'     => $attach->name,
                    'category' => $attach->category,
                    'type'     => $attach->type,
                    'mime'     => $attach->mime,
                    'url'      => $storageService->getFileUrl(
                        uuid: $attach->uuid,
                        category: $attach->category
                    ),
                ],
            ];
        })->toArray();
    }

}
