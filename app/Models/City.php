<?php

namespace App\Models;

use App\Models\Province;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasFactory;

    protected $table = 'module_city';
    protected $primaryKey = 'id_city';
    protected $guarded = ['id_city'];

    public function province()
    {
        return $this->belongsTo(Province::class, 'id_province', 'id_province');
    }
}
