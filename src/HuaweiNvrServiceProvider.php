<?php

namespace Verus\HuaweiNvr;

use Illuminate\Support\ServiceProvider;

class HuaweiNvrServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
        $this->publishes([
            __DIR__ . '/config/huaweinvr.php' => config_path("huaweinvr.php")
        ]);
        $this->commands([
            \Verus\HuaweiNvr\Command\NVRlisten::class,
        ]);
    }
}
