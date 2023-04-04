<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ForgotActivity extends Model
{
    use HasFactory;
    protected $table = 'forgot_attempts';
    protected $guarded = ['id'];
}
