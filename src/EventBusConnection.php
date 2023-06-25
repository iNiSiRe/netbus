<?php

namespace inisire\NetBus;

use React\Socket\ConnectionInterface;

class EventBusConnection extends Connection
{
    public function __construct(
        ConnectionInterface $connection,
        private array $subscribedEvents = [],
        private ?string $address = null
    )
    {
        parent::__construct($connection);
    }

    public function getSubscribedEvents(): array
    {
        return $this->subscribedEvents;
    }

    public function subscribe(array $events): void
    {
        $this->subscribedEvents = $events;
    }

    public function assignAddress(string $address): void
    {
        $this->address = $address;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }
}