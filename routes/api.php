<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ForgotController;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\MigrationController;
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
    Route::post('/email-verification', [RegistrationController::class, 'verification_email'])->name('VerificationEmail')->middleware('jwt.auth');
    Route::post('/resend-link', [RegistrationController::class, 'resend_verification_link'])->name('ResendLink')->middleware('jwt.auth');
    Route::get('/get-interest', [RegistrationController::class, 'get_interest'])->name('GetInterest')->middleware('jwt.auth');
    Route::post('/store-interest', [RegistrationController::class, 'store_interest'])->name('StoreInterest')->middleware('jwt.auth');
    Route::post('/select-interest', [RegistrationController::class, 'choose_interest'])->name('ChooseInterest')->middleware('jwt.auth');
    Route::get('/get-location', [RegistrationController::class, 'get_location'])->name('GetLocation')->middleware('jwt.auth');
    Route::post('/setup-profile', [RegistrationController::class, 'submit_profile'])->name('SubmitProfile')->middleware('jwt.auth');
    Route::get('/get-profession', [RegistrationController::class, 'get_profession'])->name('GetProfession')->middleware('jwt.auth');
    Route::post('/submit-profession', [RegistrationController::class, 'submit_profession'])->name('SubmitProfession')->middleware('jwt.auth');
    Route::get('/get-user-profile-id/{id}', [RegistrationController::class, 'get_user']);
    // Route::get('/get-user-id', [RegistrationController::class, 'get_user']);
    Route::get('/get-user-profile', [RegistrationController::class, 'get_profile_user'])->name('GetProfileUser')->middleware('jwt.auth');
});

Route::prefix('migration')->group(function () {
    Route::post('/id_job', [MigrationController::class, 'migrate_id_job']);
    Route::post('/id_company', [MigrationController::class, 'migrate_id_company']);
});
// Migration
Route::post('/migration-about-me', [MigrationController::class, 'migration_about_me'])->name('migrationAboutMe');
