<?php

namespace Wei\HuaweiNvr;

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
            \Wei\HuaweiNvr\Console\NVRlisten::class,
        ]);
    }
}
