<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageGroup extends Model
{
    use HasFactory;

    protected $table = 'module_message_room';
    protected $primaryKey = 'id_message_room';

    protected $fillable = [
        'id_user',
        'title',
        'image',
        'type',
        'description',
        'additional_data'
    ];
}
