<?php

namespace inisire\NetBus\Event\Server;

use inisire\NetBus\Command;
use inisire\NetBus\Connection;
use React\Socket\ConnectionInterface;

class EventBusConnection extends Connection
{
    private array $subscribedEvents = [];

    private ?string $address = null;

    public function __construct(
        ConnectionInterface $connection
    )
    {
        parent::__construct($connection);

        $this->on('command', function (Command $command) {
           switch ($command->getName()) {
               case 'subscribe': {
                   $data = $command->getData();
                   $this->subscribedEvents = $data['events'] ?? [];
                   break;
               }
           }
        });
    }

    public function isSubscribed(string $name): bool
    {
        foreach ($this->subscribedEvents as $event) {
            if ($event === '*' || $event === $name) {
                return true;
            }
        }

        return false;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }
}