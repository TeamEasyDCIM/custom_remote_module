<?php

namespace Modules\CustomRemoteModule;

use Illuminate\Support\ServiceProvider;

class CustomServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $this->initEvents();
    }

    /**
     * @return void
     */
    protected function initEvents()
    {
        $this->app['events']->listen('common: os.kickstart.generator', function(string $script, array $params)
        {
            adump($script);
        });
    }
}