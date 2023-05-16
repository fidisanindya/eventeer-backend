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
}
