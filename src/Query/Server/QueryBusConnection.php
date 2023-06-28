<?php

namespace inisire\NetBus\Query\Server;

use inisire\NetBus\Command;
use inisire\NetBus\Connection;
use React\Socket\ConnectionInterface;

class QueryBusConnection extends Connection
{
    private array $registeredQueries = [];

    private ?string $address = null;

    public function __construct(
        ConnectionInterface $connection
    )
    {
        parent::__construct($connection);

        $this->on('command', function (Command $command) {
            switch ($command->getName()) {
                case 'register': {
                    $data = $command->getData();
                    $this->registeredQueries = $data['queries'] ?? [];
                    $this->address = $data['address'] ?? null;
                    break;
                }
            }
        });
    }

    public function supportsQuery(string $name): bool
    {
        foreach ($this->registeredQueries as $query) {
            if ($query === $name) {
                return true;
            }
        }

        return false;
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