<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\ForgotController;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\HomepageController;
use App\Http\Controllers\MigrationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\SettingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::get('/', function () {
    return view('welcome');
})->middleware('jwt.auth');  // testing JWT Authentication

Route::prefix('auth')->group(function () {
    Route::post('/', [AuthController::class, 'login']);
    Route::post('/sso_login', [AuthController::class, 'sso_login_post']);
    Route::get('/google', [GoogleController::class, 'redirectToGoogle']); 
    Route::get('/google/callback', [GoogleController::class, 'handleGoogleCallback']); 
    Route::post('/google/callback_client', [GoogleController::class, 'handleCallbackClient']); 
});

Route::prefix('settings')->group(function () {
    Route::get('/landing_page_data', [SettingController::class, 'get_landing_page_data']);
    Route::get('/translate_landing_page', [SettingController::class, 'get_translate_landing_page']);
});

Route::prefix('forgot')->group(function () {
    Route::post('/send_forgot_email', [ForgotController::class, 'post_send_forgot_email']);
    Route::post('/check_code', [ForgotController::class, 'post_check_code']);
    Route::post('/reset_password', [ForgotController::class, 'post_reset_password']);
});

Route::prefix('registration')->group(function () {
    Route::post('', [RegistrationController::class, 'registration'])->name('Registration');
    Route::post('/v2', [RegistrationController::class, 'registration_v2'])->name('RegistrationV2');
    Route::post('/email_verification', [RegistrationController::class, 'verification_email'])->name('VerificationEmail')->middleware('jwt.auth');
    Route::post('/resend_link', [RegistrationController::class, 'resend_verification_link'])->name('ResendLink')->middleware('jwt.auth');
    Route::get('/get_interest', [RegistrationController::class, 'get_interest'])->name('GetInterest')->middleware('jwt.auth');
    Route::post('/store_interest', [RegistrationController::class, 'store_interest'])->name('StoreInterest')->middleware('jwt.auth');
    Route::post('/select_interest', [RegistrationController::class, 'choose_interest'])->name('ChooseInterest')->middleware('jwt.auth');
    Route::get('/get_location', [RegistrationController::class, 'get_location'])->name('GetLocation')->middleware('jwt.auth');
    Route::post('/setup_profile', [RegistrationController::class, 'submit_profile'])->name('SubmitProfile')->middleware('jwt.auth');
    Route::get('/get_profession', [RegistrationController::class, 'get_profession'])->name('GetProfession')->middleware('jwt.auth');
    Route::post('/submit_profession', [RegistrationController::class, 'submit_profession'])->name('SubmitProfession')->middleware('jwt.auth');
    Route::get('/get_user_profile_id/{id}', [RegistrationController::class, 'get_user']);
    // Route::get('/get-user-id', [RegistrationController::class, 'get_user']);
    Route::get('/get_user_profile', [RegistrationController::class, 'get_profile_user'])->name('GetProfileUser')->middleware('jwt.auth');
});

// Migration
Route::prefix('migrate')->group(function () {
    Route::post('/id_job', [MigrationController::class, 'migrate_id_job']);
    Route::post('/id_company', [MigrationController::class, 'migrate_id_company']);
    Route::post('/about_me', [MigrationController::class, 'migration_about_me'])->name('migrationAboutMe');
    Route::post('/group_message_to_message_room', [MigrationController::class, 'migrate_group_message_to_message_room']);
    Route::post('/group_message_rolemember_to_message_user', [MigrationController::class, 'migrate_group_message_rolemember_to_message_user']);
});

// Community List
Route::prefix('community_list')->group(function () {
    Route::middleware(['jwt.auth'])->group(function () {
        Route::get('/event_all', [CommunityController::class, 'getEventAll']);
        Route::get('/event_might_like', [CommunityController::class, 'getEventMightLike']);
        Route::get('/event_top', [CommunityController::class, 'getEventTop']);
        Route::get('/your_event', [CommunityController::class, 'getYourEvent']);
        Route::get('/community_public', [CommunityController::class, 'getCommunityPublic']);
        Route::get('/community_interest', [CommunityController::class, 'getCommunityInterest']);
        Route::get('/community_top', [CommunityController::class, 'getTopCommunity']);
    });
});

// Homepage
Route::get('/homepage', [HomepageController::class, 'get_homepage'])->middleware('jwt.auth');

// Profile
Route::prefix('profile')->group(function () {
    Route::middleware(['jwt.auth'])->group(function () {
        Route::get('/detail_portofolio', [ProfileController::class, 'detailPortfolio']);
        Route::get('/detail_certificate', [ProfileController::class, 'detailCertificate']);
        Route::get('/detail_community', [ProfileController::class, 'detailCommunity']);
        Route::get('/detail_post', [ProfileController::class, 'detailPost']);
        Route::get('/detail_activity', [ProfileController::class, 'detailActivity']);
        Route::post('/edit_profile_picture', [ProfileController::class, 'edit_profile_picture']);
        Route::post('/edit_banner_picture', [ProfileController::class, 'edit_banner']);
        Route::post('/edit_profile', [ProfileController::class, 'edit_profile']);
    });
    Route::get('/get_profile/{id}', [ProfileController::class, 'get_profile']);
    // Route::post('/add-portofolio', [ProfileController::class, 'add_portofolio']);
    // Route::post('/edit-portofolio', [ProfileController::class, 'edit_portofolio']);
    // Route::post('/delete-portofolio', [ProfileController::class, 'delete_portofolio']);
});
 