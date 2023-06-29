<?php

namespace inisire\NetBus\Event\Server;

use inisire\NetBus\Command;
use inisire\NetBus\Connection;
use inisire\NetBus\Event\EventSubscriber;
use inisire\NetBus\Event\RemoteEventInterface;

class RemoteEventSubscriber implements EventSubscriber
{
    public function __construct(
        private ?Connection $connection,
        private array $subscribedEvents = []
    )
    {
    }

    public function getSupportedEvents(): array
    {
        return $this->subscribedEvents;
    }

    public function handleEvent(RemoteEventInterface $event): void
    {
        $this->connection->send(new Command('event', [
            'sourceId' => $event->getSourceNodeId(),
            'name' => $event->getName(),
            'data' => $event->getData()
        ]));
    }
}