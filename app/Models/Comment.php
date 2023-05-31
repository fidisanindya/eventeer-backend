<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;

    protected $table = 'module_comment';
    protected $primaryKey = 'id_comment';
    protected $guarded = ['id_comment'];
}