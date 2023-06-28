<?php

namespace inisire\NetBus\Event\Server;

use inisire\NetBus\Buffer;
use inisire\NetBus\Command;
use inisire\NetBus\Event\Event;
use inisire\NetBus\Event\Server\EventBusConnection;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;

class EventBus implements EventDispatcherInterface
{
    private SocketServer $server;

    /**
     * @var array<EventBusConnection>
     */
    private array $connections = [];

    public function __construct(
        private readonly LoopInterface $loop,
        private readonly LoggerInterface $logger
    )
    {
    }

    public function start(string $address): void
    {
        $this->server = new SocketServer($address, [], $this->loop);
        $this->server->on('connection', function (ConnectionInterface $connection) {
            $this->onConnection($connection);
        });
    }

    private function handleCommand(string $id, Command $command): void
    {
        $data = $command->getData();

        switch ($command->getName()) {
            case 'event': {
                $this->handleRemoteEvent($id, new \inisire\NetBus\DTO\Event($data['name'], $data['data'] ?? []));
                break;
            }
        }
    }

    private function handleRemoteEvent(string $clientId, Event $event): void
    {
        $this->logger->debug('Event', ['from' => $clientId, 'event' => [$event->getName(), $event->getData()]]);

        $this->dispatch($event);
    }

    private function onEnd(string $id): void
    {
        unset($this->connections[$id]);
    }

    private function onConnection(ConnectionInterface $connection): void
    {
        $this->logger->debug('Connected', ['remote' => $connection->getRemoteAddress()]);

        $id = spl_object_id($connection);
        $this->connections[$id] = new EventBusConnection($connection);

        $connection->on('command', function (Command $command) use ($id) {
            $this->handleCommand($id, $command);
        });

        $connection->on('end', function () use ($id) {
            $this->onEnd($id);
        });
    }

    public function dispatch(object $event)
    {
        if (!$event instanceof Event) {
            $this->logger->error('Bad event', ['class' => $event::class, 'expected' => Event::class]);
            return;
        }

        foreach ($this->connections as $connection) {
            if ($connection->isSubscribed($event->getName())) {
                $connection->send(new Command('event', [
                    'name' => $event->getName(),
                    'data' => $event->getData()
                ]));
            }
        }
    }
}