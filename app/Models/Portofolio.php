<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Portofolio extends Model
{
    use HasFactory;

    public $timestamps = false;
    
    protected $fillable = [
        'project_name',
        'project_url',
        'start_date',
        'end_date',
        'id_user'
    ];

    protected $table = 'module_portofolio';
}
