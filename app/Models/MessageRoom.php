<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageRoom extends Model
{
    use HasFactory;

    protected $table = 'module_message_room';
    protected $primaryKey = 'id_message_room';
    protected $guarded = ['id_message_room'];

    public function message_user(){
        return $this->hasMany(MessageUser::class, 'id_message_room', 'id_message_room');
    }
    
    public function message_pin(){
        return $this->hasMany(MessagePin::class, 'id_message_room', 'id_message_room');
    }

    public function message(){
        return $this->hasMany(Message::class, 'id_message_room', 'id_message_room');
    }
}
