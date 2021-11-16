<?php

namespace Wei\HuaweiNvr;

use Illuminate\Support\Facades\Facade;

class NvrFacade extends Facade
{
    public static function getFacadeAccessor(): string
    {
        return 'HuaweiNvr';
    }
}
