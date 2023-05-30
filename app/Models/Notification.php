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
}
