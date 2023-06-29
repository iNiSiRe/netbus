<?php

namespace inisire\NetBus\Event;

use inisire\NetBus\DTO\RemoteEvent;
use inisire\NetBus\Event\Server\EventSource;
use Psr\EventDispatcher\EventDispatcherInterface;

class InternalEventSource extends EventSource implements EventDispatcherInterface
{
    public function __construct(
        private readonly string $sourceAddress
    )
    {
    }

    public function dispatch(object $event)
    {
        if (!$event instanceof Event) {
            throw new \RuntimeException();
        }

        $this->emit('event', [new RemoteEvent($this->sourceAddress, $event->getName(), $event->getData())]);
    }
}