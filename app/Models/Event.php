<?php

namespace App\Models;

use App\Models\Community;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Event extends Model
{
    use HasFactory;

    protected $table = 'module_event';

    public function community(){
        return $this->belongsTo(Community::class, 'id_community', 'id_community');
    }
}
