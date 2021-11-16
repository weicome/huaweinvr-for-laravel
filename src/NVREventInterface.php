<?php

namespace Wei\HuaweiNvr;

interface NVREventInterface
{
    public function handler(array $event, array $channel);
}
