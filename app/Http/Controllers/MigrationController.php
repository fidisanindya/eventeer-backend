<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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

    public function migrate_id_job(Request $request){
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'      => 422,
                'status'    => 'failed',
                'result'    => $validator->messages(),
            ], 422);
        }

        $limit = $request->limit;

        $userProfile = UserProfile::where('key_name', 'id_job')->limit($limit)->get();

        if($userProfile->first() != null) {
            foreach ($userProfile as $up){
                User::where('id_user', $up->id_user)->update([
                    'id_job'    => $up->value,
                ]);;
                
                UserProfile::where('id_user_profile', $up->id_user_profile)->delete();
            }
    
            return response()->json([
                'code'  => 200,
                'status'=> 'success',
                'result'=> 'Migrated ' . $limit . ' data  successfully'
            ], 200);
        }

        return response()->json([
            'code'  => 500,
            'status'=> 'failed',
            'result'=> 'No data to migrate'
        ], 500);
    }

    public function migrate_id_company(Request $request){
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'      => 422,
                'status'    => 'failed',
                'result'    => $validator->messages(),
            ], 422);
        }
        
        $limit = $request->limit;

        $userProfile = UserProfile::where('key_name', 'id_company')->limit($limit)->get();
        
        if($userProfile->first() != null){
            foreach ($userProfile as $up){
                User::where('id_user', $up->id_user)->update([
                    'id_company'    => $up->value,
                ]);
    
                UserProfile::where('id_user_profile', $up->id_user_profile)->delete();
            }
    
            return response()->json([
                'code'  => 200,
                'status'=> 'success',
                'result'=> 'Migrated ' . $limit . ' data successfully'
            ]);
        }

        return response()->json([
            'code'  => 500,
            'status'=> 'failed',
            'result'=> 'No data to migrate'
        ], 500);
    }
}
