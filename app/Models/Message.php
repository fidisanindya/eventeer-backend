<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model as EloquentModel;

class Message extends EloquentModel
{
    protected $connection = 'mongodb';
    
    protected $collection = 'chat';

    public $incrementing = true;
    
    protected $fillable = ['text', 'type', 'id_user', 'id_message_room']; 

    protected $primaryKey = '_id';

    public function message_room()
    {
        return $this->belongsTo(MessageRoom::class, 'id_message_room', 'id_message_room');
    }

    public function user(){
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
