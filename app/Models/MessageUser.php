<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageUser extends Model
{
    use HasFactory;

    protected $table = 'module_message_user';
    protected $primaryKey = 'id_message_user';
    protected $guarded = ['id_message_user'];

    public function message_room(){
        return $this->belongsTo(MessageRoom::class, 'id_message_room', 'id_message_room');
    }

    public function user(){
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
