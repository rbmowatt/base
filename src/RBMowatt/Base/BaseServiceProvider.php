<?php

namespace RBMowatt\Base;

use Illuminate\Routing\Route;
use Illuminate\Support\ServiceProvider;
use RBMowatt\Base\Controllers\Api\BaseApiController;
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
    * A Route builds its controller once and keeps it, so without a flush the
    * second request on a route gets the first request's controller, and with it
    * whatever its constructor captured: $request, $user, an injected QueryParser,
    * the ApiResponse with its href and responseId. Octane flushes the controller
    * after every request; the HTTP test client does not, so a second get() in one
    * test reads the first one's headers and user. This does Octane's flush on
    * terminate, limited to this package's controllers.
    *
    * @return void
    */
    public function boot()
    {
        $this->app->terminating(function () {
            $route = $this->app->bound('request') ? $this->app['request']->route() : null;

            if ($route instanceof Route && is_a((string) $route->getControllerClass(), BaseApiController::class, true))
            {
                $route->flushController();
            }
        });
    }
}
