<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FileAttachment extends Model
{
    use HasFactory;

    protected $table = 'module_file_attachment';
    protected $primaryKey = 'id_file_attachment';
    protected $guarded = ['id_file_attachment'];
}
