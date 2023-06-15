<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunityManager extends Model
{
    use HasFactory;

    protected $table = 'module_community_manager';
    protected $primaryKey = 'id_community_manager';
    protected $guarded = ['id_community_manager'];

}
