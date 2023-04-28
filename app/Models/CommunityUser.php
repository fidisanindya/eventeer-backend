<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunityUser extends Model
{
    use HasFactory;
    
    protected $table = 'module_community_user';

    public function community(){
        return $this->belongsTo(Community::class, 'id_community', 'community_id');
    }
}
