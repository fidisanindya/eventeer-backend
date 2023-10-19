<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Province extends Model
{
    use HasFactory;

    protected $table = 'module_province';
    protected $primaryKey = 'id_province';
    protected $guarded = ['id_province'];
}
