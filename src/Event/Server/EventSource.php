<?php

namespace inisire\NetBus\Event\Server;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;

abstract class EventSource implements EventEmitterInterface
{
    use EventEmitterTrait;

    public function subscribe(callable $listener)
    {
        $this->on('event', $listener);
    }
}