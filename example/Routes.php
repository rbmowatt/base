<?php

use Illuminate\Support\Facades\Route;
use Example\Controllers\Api\ExampleApiController;

// Laravel 8 dropped string controller references, so the class is named directly.
// The guard here has to exist in your config/auth.php.
Route::group(['middleware' => ['api', 'auth:sanctum'], 'prefix' => 'api'], function () {
    Route::apiResource('example', ExampleApiController::class);
});
