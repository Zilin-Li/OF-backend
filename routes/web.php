<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WorkflowAuth\AuthorizationController;
use App\Http\Controllers\WorkflowAuth\CallbackController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Route::get('test', function(){
//   return "text";
// });

Route::get('authorization',  [AuthorizationController::class, 'authorization']);

Route::get('callback',  [CallbackController::class, 'callback']);

// Route::get('callback', function () {
//     return redirect('callback.php');
// });
