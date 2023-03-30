<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ForgotController;
use App\Http\Controllers\SettingController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
})->middleware('jwt.auth');  // testing JWT Authentication

Route::post('/auth', [AuthController::class, 'login']);

Route::prefix('settings')->group(function () {
    Route::get('/landing_page_data', [SettingController::class, 'get_landing_page_data']);
});
Route::prefix('forgot')->group(function () {
    Route::post('/send_forgot_email', [ForgotController::class, 'post_send_forgot_email']);
    Route::post('/check_code', [ForgotController::class, 'post_check_code']);
    Route::post('/reset_password', [ForgotController::class, 'post_reset_password']);
});
Route::prefix('registration')->group(function () {
    Route::post('', [RegistrationController::class, 'registration'])->name('Registration');
    Route::post('/email-verification', [RegistrationController::class, 'verification_email'])->name('VerificationEmail');
    Route::post('/resend-link', [RegistrationController::class, 'resend_verification_link'])->name('ResendLink');
    Route::get('/get-interest', [RegistrationController::class, 'get_interest'])->name('GetInterest');
    Route::post('/select-interest', [RegistrationController::class, 'choose_interest'])->name('ChooseInterest');
    Route::get('/get-location', [RegistrationController::class, 'get_location'])->name('GetLocation');
    Route::post('/setup-profile', [RegistrationController::class, 'submit_profile'])->name('SubmitProfile');
    Route::get('/get-profession', [RegistrationController::class, 'get_profession'])->name('GetProfession');
    Route::post('/submit-profession', [RegistrationController::class, 'submit_profession'])->name('SubmitProfession');
    Route::get('/get-user-profile-id', [RegistrationController::class, 'get_profile_user_id'])->name('GetProfileUserID');
    Route::get('/get-user-profile', [RegistrationController::class, 'get_profile_user'])->name('GetProfileUser')->middleware('jwt.auth');
});
