<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessagePin extends Model
{
    use HasFactory;

    protected $table = 'module_message_pin';
    protected $primaryKey = 'id_message_pin';
    protected $guarded = ['id_message_pin'];

    public function message_room(){
        return $this->belongsTo(MessageRoom::class, 'id_message_room', 'id_message_room');
    }
}
