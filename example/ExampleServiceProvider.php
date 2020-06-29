<?php
namespace Example;

use Illuminate\Support\ServiceProvider;

class ExampleServiceProvider extends ServiceProvider
{

    /**
    * Indicates if loading of the provider is deferred.
    *
    * @var bool
    */
    protected $defer = false;

    /**
    * Bootstrap the application events.
    *
    * @return void
    */
    public function boot()
    {
        include 'Routes.php';
    }

    /**
    * Get the services provided by the provider.
    *
    * @return array
    */
    public function provides()
    {
        return array();
    }

}
