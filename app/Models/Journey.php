<?php

namespace App\Models;

use App\Models\User;
use App\Models\Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Journey extends Model
{
    use HasFactory;

    protected $table = 'module_journey';
    protected $primaryKey = 'id_journey';
    protected $guarded = ['id_journey'];

    public function user(){
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function event(){
        return $this->belongsTo(Event::class, 'id_event', 'id_event');
    }
}
