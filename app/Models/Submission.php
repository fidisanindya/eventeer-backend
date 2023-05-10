<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    use HasFactory;

    protected $table = 'module_submission';
    protected $primaryKey = 'id_submission';
    protected $guarded = ['id_submission'];

    public function event(){
        return $this->belongsTo(Event::class, 'id_event', 'id_event');
    }
}
