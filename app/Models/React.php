<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class React extends Model
{
    use HasFactory;

    protected $table = 'module_react';
    protected $primaryKey = 'id_react';
    protected $guarded = ['id_react'];
    public $timestamps = false;
}
