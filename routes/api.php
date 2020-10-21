<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WorkflowAuth\RequestController;

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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
Route::get('searchjob',  [RequestController::class, 'searchJob']);
// Route::get('update',  [RequestController::class, 'updateToWM']);
// Route::get('syncdata',  [RequestController::class, 'syncData']);
Route::post('syncdata',  [RequestController::class, 'syncData']);
