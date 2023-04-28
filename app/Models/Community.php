<?php

namespace App\Models;

use App\Models\CommunityUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Community extends Model
{
    use HasFactory;

    protected $table = 'module_community';

    public function community_interest(){
        return $this->hasMany(CommunityInterest::class, 'community_id', 'id_community');
    }

    public function community_user(){
        return $this->hasMany(CommunityUser::class, 'id_community', 'id_community');
    }
}
