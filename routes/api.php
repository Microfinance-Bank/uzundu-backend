<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controller\CustomerStakeController;

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
//Route::get('/win/list', ['App\Http\Controllers\WinListController', 'index']);

// this is the part that is listing all categorie
Route::get('/categories/list', ['App\Http\Controllers\CategoriesController', 'index']);

//Route::get('retrieve', [CustomerStakeController::class, 'index']);
Route::group(['prefix' => 'v1/'], function () {
    Route::post('/login', ['App\Http\Controllers\Auth\AuthController', 'login']);
    Route::post('/register', ['App\Http\Controllers\Auth\AuthController', 'registerUser']);
    Route::get('/get-account/{phone}', ['App\Http\Controllers\GeneralController', 'getAccountDetails']);
    Route::get('/get-banks', ['App\Http\Controllers\GeneralController', 'getBanks']);
    Route::post('/verify-account', ['App\Http\Controllers\GeneralController', 'verifyAccount']);

    Route::patch('/create-pin/{username}', ['App\Http\Controllers\Auth\AuthController', 'createPin']);

    Route::group(['middleware'=>['auth:sanctum']],function() {
        Route::get('/logout', ['App\Http\Controllers\Auth\AuthController', 'logout']);
        Route::post('/single-transfer', ['App\Http\Controllers\TransferController', 'singleTransfer']);
    });
});
