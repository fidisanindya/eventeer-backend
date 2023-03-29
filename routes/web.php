<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
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