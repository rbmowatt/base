<?php

namespace Example;

use Illuminate\Support\ServiceProvider;
use Example\Models\ExampleModel;
use Example\Models\Interfaces\ExampleModelInterface;

class ExampleServiceProvider extends ServiceProvider
{
    /**
    * Register bindings.
    *
    * ExampleService type-hints the interface, so the container needs to know which
    * model satisfies it. Swap the concrete class here and nothing else changes.
    *
    * @return void
    */
    public function register()
    {
        $this->app->bind(ExampleModelInterface::class, ExampleModel::class);
    }

    /**
    * Bootstrap the application events.
    *
    * @return void
    */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/Routes.php');
    }
}
