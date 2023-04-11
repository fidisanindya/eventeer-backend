<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;

class MigrationController extends Controller
{
    public function migration_about_me(Request $request){
        $request->validate([
            'limit' => 'required'
        ]);
        
        $user = UserProfile::select('*')->where('key_name', 'about_me')->limit($request->limit)->get();
        
        foreach($user as $us){
            $query = User::where('id_user', $us->id_user)->update([
                'bio' => $us->value
            ]);
            
            if($query){
                UserProfile::where('id_user_profile', $us->id_user_profile)->delete();
            }
        }

        return response()->json([
            'code' => 200,
            'status' => 'success migration',
        ], 200);
    }
}
