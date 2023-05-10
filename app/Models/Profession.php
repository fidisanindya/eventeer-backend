<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profession extends Model
{
    use HasFactory;

    protected $table = 'module_job';
    protected $primaryKey = 'id_job';
    protected $guarded = ['id_job'];
}
