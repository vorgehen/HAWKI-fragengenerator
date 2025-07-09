<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\StorageServices\StorageServiceFactory;

class AiConvMsg extends Model
{
    use HasFactory;

    protected $fillable = [
        'conv_id',
        'user_id',
        'message_role',
        'message_id',
        'model',
        'iv',
        'tag',
        'content',
        'completion',
    ];

    // Define the relationship with AiConv
    public function conversation()
    {
        return $this->belongsTo(AiConv::class, 'conv_id');
    }

    public function user(){
        return $this->belongsTo(User::class);
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
