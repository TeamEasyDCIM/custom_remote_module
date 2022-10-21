<?php

namespace Modules\CustomRemoteModule;

use Illuminate\Support\ServiceProvider;
use Modules\CustomRemoteModule\Kickstart\CustomKickstart;

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
            $netmask = array_get($params, 'network.netmask');

            $cidrMask = $this->mask2cidr($netmask);

            $script = str_replace('{{custom:cidrMask}}', $cidrMask, $script);

            if(array_get($params, 'template.tags') == 'customtag') {
                $kickstart = new CustomKickstart($script, $params);
                $script = $kickstart->generate();
            }

            return $script;
        });
    }

    /**
     * @param $mask
     * @return int
     */
    private function mask2cidr($mask) 
    {
        $long = ip2long($mask);
        $base = ip2long('255.255.255.255');
        return 32-log(($long ^ $base)+1,2);
    }
}