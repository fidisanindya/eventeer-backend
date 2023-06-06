<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'module_notif';
    protected $primaryKey = 'id_notif';
    protected $guarded = ['id_notif'];
    public $timestamps = false;

    public function user(){
        return $this->belongsTo(User::class, 'notif_from', 'id_user');
    }

    public function community(){
        return $this->belongsTo(Community::class, 'notif_from', 'id_community');
    }
}
