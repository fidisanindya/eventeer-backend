<?php

namespace App\Models;

use App\Models\Interest;
use App\Models\Community;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CommunityInterest extends Model
{
    use HasFactory;

    protected $table = 'module_community_interest';

    public function community(){
        return $this->belongsTo(Community::class, 'id_community', 'community_id');
    }
    public function interest(){
        return $this->belongsTo(Interest::class, 'id_interest', 'id_interest');
    }
}
