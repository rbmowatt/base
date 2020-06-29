<?php
Route::group(['middleware' => ['web','auth:api'], 'prefix' => 'api'], function () {
    Route::resource('example', 'Example\Controllers\Api\ExampleApiController');
});
