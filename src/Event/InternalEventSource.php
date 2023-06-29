<?php

namespace inisire\NetBus\Event;

use inisire\NetBus\Event\RemoteEvent;
use inisire\NetBus\Event\Server\EventSource;
use Psr\EventDispatcher\EventDispatcherInterface;

class InternalEventSource extends EventSource implements EventDispatcherInterface
{
    public function __construct(
        private readonly string $nodeId
    )
    {
    }

    public function dispatch(object $event)
    {
        if (!$event instanceof EventInterface) {
            throw new \RuntimeException();
        }

        $this->emit('event', [new RemoteEvent($this->nodeId, $event->getName(), $event->getData())]);
    }
}