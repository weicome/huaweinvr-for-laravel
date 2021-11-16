<?php

namespace Wei\HuaweiNvr\Facades;

use Illuminate\Support\Facades\Facade;

class NvrFacades extends Facade
{
    public static function getFacadeAccessor()
    {
        return 'huaweinvr';
    }
}
