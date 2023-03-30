<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_user',
        'user_agent',
        'ipv4_address',
        'is_successful'
    ];

    protected $table = 'login_attempts';
}
