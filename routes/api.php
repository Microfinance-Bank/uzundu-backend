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
Route::get('/categories/list', function (){
    return 32;
});

Route::group(['prefix' => 'v1/'], function () {
    Route::post('/login', ['App\Http\Controllers\Auth\AuthController', 'login']);
    Route::post('/register', ['App\Http\Controllers\Auth\AuthController', 'registerUser']);
    Route::get('/get-account/{phone}', ['App\Http\Controllers\GeneralController', 'getAccountDetails']);
    Route::post('/verify-account', ['App\Http\Controllers\GeneralController', 'verifyAccount']);
    Route::get('/refresh-token',  ['App\Http\Controllers\Auth\AuthController', 'refreshToken'])->middleware(['auth:sanctum',  'ability:'.\App\Enums\TokenAbility::ISSUE_ACCESS_TOKEN->value,]);

    Route::patch('/create-pin/{username}', ['App\Http\Controllers\Auth\AuthController', 'createPin']);

    Route::group(['middleware'=>['auth:sanctum']],function() {
        Route::get('/get-banks', ['App\Http\Controllers\GeneralController', 'getBank']);
        Route::post('/verify-pin', ['App\Http\Controllers\Auth\AuthController', 'verifyPin']);
        Route::get('/get-balance', ['App\Http\Controllers\GeneralController', 'getBalance']);
        Route::get('/get-transfers', ['App\Http\Controllers\TransferController', 'getTransfers']);
        Route::post('/single-transfer', ['App\Http\Controllers\TransferController', 'singleTransfer']);
        Route::post('/bulk-transfer', ['App\Http\Controllers\TransferController', 'bulkTransfer']);
        Route::get('/logout', ['App\Http\Controllers\Auth\AuthController', 'logout']);
        Route::get('/get-dashboard-data', ['App\Http\Controllers\TransferController', 'getDashboardData']);

    });
    Route::group(['prefix' => 'admin/'], function () {
        Route::post('/login', ['App\Http\Controllers\Auth\AuthController', 'login']);
        Route::group(['middleware'=>['auth:sanctum']],function() {
            Route::get('/transfers', ['App\Http\Controllers\TransferController', 'getIntraTransfers']);
            Route::get('/get-inter-transfers', ['App\Http\Controllers\TransferController', 'getInterTransfers']);
            Route::get('/get-dashboard-data', ['App\Http\Controllers\AdminController', 'getDashboardData']);
            Route::get('/logout', ['App\Http\Controllers\Auth\AuthController', 'logout']);


        });
    });
});
