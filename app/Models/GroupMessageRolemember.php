<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupMessageRolemember extends Model
{
    use HasFactory;

    protected $table = 'module_group_message_rolemember';
    protected $primaryKey = 'id_group_member';
    protected $guarded = ['id_group_member'];
}
