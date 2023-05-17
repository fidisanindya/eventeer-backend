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
}
