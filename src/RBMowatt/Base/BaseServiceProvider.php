<?php

namespace RBMowatt\Base;

use Illuminate\Support\ServiceProvider;
use RBMowatt\Base\Rest\ApiResponse;
use RBMowatt\Base\Rest\Interfaces\ApiResponseInterface;

class BaseServiceProvider extends ServiceProvider
{
    /**
    * Register any application services.
    *
    * BaseApiController type-hints the interface, and Laravel resolves controller
    * constructor dependencies out of the container, so without this binding every
    * subclass fails to resolve. Rebind it to swap the envelope implementation.
    *
    * @return void
    */
    public function register()
    {
        $this->app->bind(ApiResponseInterface::class, ApiResponse::class);
    }

    /**
    * Bootstrap any application services.
    *
    * @return void
    */
    public function boot()
    {
    }
}
