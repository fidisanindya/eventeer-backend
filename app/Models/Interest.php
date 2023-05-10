<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Interest extends Model
{
    use HasFactory;

    protected $table = 'module_interest';
    protected $primaryKey = 'id_interest';
    protected $guarded = ['id_interest'];

    public function community_interest(){
        return $this->hasMany(CommunityInterest::class, 'id_interest', 'id_interest');
    }
}
