<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Portofolio extends Model
{
    use HasFactory;

    protected $table = 'module_portofolio';
    protected $primaryKey = 'id_portofolio';
    protected $guarded = ['id_portofolio'];
}
