<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimelineShare extends Model
{
    use HasFactory;

    protected $table = 'module_timeline_share';
    protected $primaryKey = 'id_timeline_share';
    protected $guarded = ['id_timeline_share'];

    public function timeline(){
        return $this->belongsTo(Timeline::class, 'id_timeline', 'id_timeline');
    }
}
