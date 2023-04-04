<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailVerifActivity extends Model
{
    use HasFactory;
    protected $table = 'email_verif_attempt';
    protected $guarded = ['id'];
}
