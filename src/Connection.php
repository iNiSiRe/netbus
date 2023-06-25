<?php

namespace inisire\NetBus;

use React\Socket\ConnectionInterface;

class Connection
{
    public function __construct(
        private ConnectionInterface $connection,
    )
    {
    }

    public function send(Command $command): void
    {
        $serialized = json_encode([
            'x' => $command->getName(),
            'd' => $command->getData()
        ]);

        $this->connection->write($serialized . PHP_EOL);
    }
}