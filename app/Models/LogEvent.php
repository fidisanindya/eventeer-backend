<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LogEvent extends Model
{
    use HasFactory;

    protected $table = 'log_event';
    protected $primaryKey = 'id_log_event';
    protected $guarded = ['id_log_event'];
    public $timestamps = false;
}
