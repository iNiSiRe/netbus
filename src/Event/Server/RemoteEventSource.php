<?php

namespace inisire\NetBus\Event\Server;

use inisire\NetBus\Command;
use inisire\NetBus\Connection;
use inisire\NetBus\DTO\RemoteEvent;

class RemoteEventSource extends EventSource
{
    public function __construct(
        private readonly Connection $connection
    )
    {
        $this->connection->on('command', function (Command $command) {
            if ($command->getName() === 'event') {
                $data = $command->getData();
                $this->emit('event', [new RemoteEvent($data['from'], $data['name'], $data['data'])]);
            }
        });
    }
}