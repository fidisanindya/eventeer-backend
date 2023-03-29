<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailQueue extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $table = 'system_email_queue';
    protected $guarded = ['id'];
}
