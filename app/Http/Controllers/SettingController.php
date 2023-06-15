<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Setting;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use \stdClass;

class SettingController extends Controller
{
    public function get_landing_page_data(){
        $total_users = User::count();
        $about = Setting::where('setting_name', 'landing_page')->where('key_name', 'about')->first();
        $partner = Setting::where('setting_name', 'landing_page')->where('key_name', 'partner')->first();
        $testimonial = Setting::where('setting_name', 'landing_page')->where('key_name', 'testimonial')->first();

        $result = [
            'total_users'   => $total_users,
            'partners'      => json_decode($partner->value),
            'achievements'  => [
                'total_users'       => $total_users,
                'connected_users'   => 10000,
                'bulit_communities' => 500,
                'successful_events' => 7000,
            ],
            'testimonial'   => json_decode($testimonial->value),
            'about'         => json_decode($about->value),
        ];

        return response_json(200, 'success', $result);
    }

    public function get_translate_landing_page(){
        $translate = Setting::where('setting_name', 'landing_page')->where('key_name', 'translate')->first();

        $result = [
            'translate_testimonial' => json_decode($translate->value)
        ];

        return response_json(200, 'success', $result);
    }
}
