<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Interest extends Model
{
    use HasFactory;

    protected $fillable = [
        'interest_name',
        'created_by',
        'icon'
    ];

    protected $table = 'module_interest';

    public function community_interest(){
        return $this->hasMany(CommunityInterest::class, 'id_interest', 'id_interest');
    }
}
