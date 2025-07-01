<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    protected $fillable = 
    [
        'uuid', 
        'name', 
        'category',
        'type'
    ];

    // Let Attachment belong to ANY attachable model (Message, AiConvMsg)
    public function attachable()
    {
        return $this->morphTo();
    }
}
