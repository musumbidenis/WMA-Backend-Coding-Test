<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::group(['middleware' => ['web']], function () {

    Route::any('/user', [PaymentController::class, 'user']);
    Route::any('/change_status', [PaymentController::class, 'changeStatus']);
    Route::any('/subscribe', [PaymentController::class, 'subscribe']);
    Route::any('/activate', [PaymentController::class, 'activate']);

});
