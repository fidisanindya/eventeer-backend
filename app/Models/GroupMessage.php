<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupMessage extends Model
{
    use HasFactory;

    protected $table = 'module_group_message';
    protected $primaryKey = 'id_groupmessage';
    protected $guarded = ['id_groupmessage'];
}
