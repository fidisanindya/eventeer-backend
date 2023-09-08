<?php

namespace App\Models;

use App\Models\Job;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobUser extends Model
{
    use HasFactory;

    protected $table = 'module_job_user';
    protected $primaryKey = 'id_job_user';
    protected $guarded = ['id_job_user'];

    public function job(){
        return $this->belongsTo(Job::class, 'id_job', 'id_job');
    }
}
