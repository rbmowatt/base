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
        // functions.php is pulled in by composer's "files" autoloader, not from here.
        // Requiring it on boot double-declares its helpers when the provider is
        // registered more than once (package discovery plus an explicit config entry).
    }
}
