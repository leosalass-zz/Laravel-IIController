<?php

namespace Immersioninteractive\GenericController;

use Illuminate\Support\ServiceProvider;

class IIControllerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(IIController::class, function () {
            return new IIController();
        });
        $this->app->alias(IIController::class, 'IIController');
    }
}
