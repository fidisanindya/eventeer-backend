<?php

namespace App\Models;

use App\Models\Community;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Event extends Model
{
    use HasFactory;

    protected $table = 'module_event';
    protected $primaryKey = 'id_event';
    protected $guarded = ['id_event'];

    public function community(){
        return $this->belongsTo(Community::class, 'id_community', 'id_community');
    }

    public function submission(){
        return $this->hasMany(Submission::class, 'id_event', 'id_event');
    }

    public function vendor(){
        return $this->belongsTo(Vendor::class, 'vendor_id', 'id_vendor');
    }
}
