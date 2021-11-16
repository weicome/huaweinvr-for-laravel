<?php

namespace Wei\HuaweiNvr;

interface NVRInterface
{
    public function handler(array $event, array $channel, ?object $nvr = null);
}
