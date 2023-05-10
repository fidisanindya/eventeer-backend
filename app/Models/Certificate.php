<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    use HasFactory;

    protected $table = 'module_certificate';
    protected $primaryKey = 'id_certificate';
    protected $guarded = ['id_certificate'];

    public function submission(){
        return $this->belongsTo(Submission::class, 'id_submission', 'id_submission');
    }
}
