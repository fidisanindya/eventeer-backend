<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_user',
        'key_name',
        'value',
    ];

    protected $table = 'system_users_profile';
    protected $primaryKey = 'id_user_profile';
}
