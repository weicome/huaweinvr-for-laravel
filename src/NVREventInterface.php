<?php

namespace Verus\HuaweiNvr;

interface NVREventInterface
{
    public function handler(array $event, array $channel);
}
