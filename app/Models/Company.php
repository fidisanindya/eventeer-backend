<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $table = 'module_company';
    protected $primaryKey = 'id_company';
    protected $guarded = ['id_company'];
}
