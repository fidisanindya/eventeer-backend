<?php

namespace App\Models;

use App\Models\React;
use App\Models\Comment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Timeline extends Model
{
    use HasFactory;

    protected $table = 'module_timeline';
    protected $primaryKey = 'id_timeline';
    protected $guarded = ['id_timeline'];
    public $timestamps = false;

    public function react(){
        return $this->hasMany(React::class, 'id_related_to', 'id_timeline');
    }

    public function comment(){
        return $this->hasMany(Comment::class, 'id_related_to', 'id_timeline');
    }
    
    public function community(){
        return $this->hasOne(Event::class, 'id_community', 'id_community');
    }

    public function user(){
        return $this->hasOne(User::class, 'id_user', 'id_user');
    }

    public function file_attachment(){
        return $this->hasMany(FileAttachment::class, 'id_related_to', 'id_timeline');
    }
}
