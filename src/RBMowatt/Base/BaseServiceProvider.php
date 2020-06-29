<?php

namespace RBMowatt\Base;

use Illuminate\Support\ServiceProvider;

class BaseServiceProvider extends ServiceProvider
{
    /**
    * Bootstrap any application services.
    *
    * @return void
    */
    public function boot()
    {
        require_once 'functions.php';
    }
}
