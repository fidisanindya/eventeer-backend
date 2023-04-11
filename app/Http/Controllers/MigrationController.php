<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class MigrationController extends Controller
{
    public function migrate_id_job(Request $request){
        $limit = $request->limit;

        $userProfile = UserProfile::where('key_name', 'id_job')->limit($limit)->get();

        foreach ($userProfile as $up){
            $user = User::where('id_user', $up->id_user)->first();

            if($user != null){
                User::where('id_user', $up->id_user)->update([
                    'id_job'    => $up->value,
                ]);

                UserProfile::where('id_user_profile', $up->id_user_profile)->delete();
            }
        }

        return response()->json([
            'code'  => 200,
            'status'=> 'success',
            'result'=> 'Migrated successfully'
        ]);
    }
}
