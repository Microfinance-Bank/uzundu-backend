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

Route::
        namespace('App\Http\Controllers')->group(function () {
            Route::group(['prefix' => 'v1/admin'], function () {
                Route::post('/login', ['App\Http\Controllers\Auth\AuthController', 'login']);
                Route::get('/register-user', ['App\Http\Controllers\Auth\AuthController', 'registerUser']);
                Route::get('/test', ['App\Http\Controllers\Auth\AuthController', 'test']);

                Route::group(['middleware' => ['auth:sanctum']], function () {
                    Route::get('/category/create', ['App\Http\Controllers\CategoriesController', 'store']);
                    Route::get('/sub-category/create', ['App\Http\Controllers\SubCategoryController', 'store']);
                    Route::post('/winning-tags/create', ['App\Http\Controllers\WinningTagsController', 'store']);
                    Route::get('/customer-stake', ['App\Http\Controllers\CustomerStakeController', 'index']);
                    Route::get('/customer-stakefilter', ['App\Http\Controllers\CustomerStakeController', 'customers_by_filter']);
                    Route::get('/customer-stake', ['App\Http\Controllers\CustomerStakeController', 'show']);
                    Route::get('/customer-stake/totalstake', ['App\Http\Controllers\CustomerStakeController', 'getTotalStakes']);
                    Route::get('/winning-tags', ['App\Http\Controllers\WinningTagsController', 'index']);
                    Route::get('/categories', ['App\Http\Controllers\CategoriesController', 'index']);

                    Route::post('/stake/numbers/add', ['App\Http\Controllers\StakeNumbersController', 'store']);
                    Route::get('/stake/numbers', ['App\Http\Controllers\StakeNumbersController', 'index']);
                    Route::post('/customer/wins', ['App\Http\Controllers\WinListController', 'index']);
                    Route::get('/customer/winners', ['App\Http\Controllers\WinListController', 'getAllWinners']);
                    Route::post('/customer/winners-by-date', ['App\Http\Controllers\WinListController', 'getAllWinnersByDate']);
                    Route::post('/customer/winners-by-category', ['App\Http\Controllers\WinListController', 'getAllWinnersByCategory']);
                    Route::post('/customer/add', ['App\Http\Controllers\NewCustomerController', 'store']);
                    Route::get('/customer/stakes', ['App\Http\Controllers\CustomerStakeController', 'index']);
                    Route::post('/customer/stake/add', ['App\Http\Controllers\CustomerStakeController', 'store']);
                    Route::post('/customer/stake/get-user-stakes', ['App\Http\Controllers\CustomerStakeController', 'getUserStakes']);
                });
            });
        });