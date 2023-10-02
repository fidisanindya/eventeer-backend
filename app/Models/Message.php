<?php

namespace App\Models;
use MongoDB\Laravel\Eloquent\Model;

class Message extends Model
{
    protected $connection = 'mongodb';
    
    protected $collection = 'chat';

    public $incrementing = true;
    
    protected $fillable = ['text', 'file', 'type', 'id_user', 'id_message_room']; 

    protected $primaryKey = '_id';

    public function message_room()
    {
        return $this->belongsTo(MessageRoom::class, 'id_message_room', 'id_message_room');
    }

    public function user(){
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
