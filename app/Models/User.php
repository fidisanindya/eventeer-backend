<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Company;
use App\Models\Profession;
use App\Models\LoginActivity;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'system_users';
    protected $primaryKey = 'id_user';
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'full_name',
        'email',
        'email_status',
        'id_social',
        'password',
        'activation_code',
        'sso_id',
        'email_status',
        'registered_via',
        'access',
        'profile_picture',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function login_activities()
    {
        return $this->hasMany(LoginActivity::class, 'id_user', 'id_user');
    }

    public function job(){
        return $this->belongsTo(Profession::class, 'id_job', 'id_job');
    }
    
    public function company(){
        return $this->belongsTo(Company::class, 'id_company', 'id_company');
    }
}
