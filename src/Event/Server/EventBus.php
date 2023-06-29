<?php

namespace inisire\NetBus\Event\Server;

use inisire\NetBus\Buffer;
use inisire\NetBus\Command;
use inisire\NetBus\Connection;
use inisire\NetBus\Event\Event;
use inisire\NetBus\Event\EventSubscriber;
use inisire\NetBus\Event\RemoteEventInterface;
use inisire\NetBus\Event\Server\EventBusConnection;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;

class EventBus
{
    private SocketServer $server;

    /**
     * @var array<string, EventSubscriber>
     */
    private array $subscribers = [];

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

    private function handleRemoteSubscribe(Connection $connection, array $events = []): void
    {
        $subscriber = new RemoteEventSubscriber($connection, $events);

        $this->subscribers[$connection->getId()] = $subscriber;

        $connection->on('end', function () use ($connection) {
            unset($this->subscribers[$connection->getId()]);
        });
    }

    private function handleRemoteCommand(Connection $connection, Command $command): void
    {
        switch ($command->getName()) {
            case 'subscribe': {
                $data = $command->getData();
                $this->handleRemoteSubscribe($connection, $data['events'] ?? []);
                break;
            }
        }
    }

    private function handleEvent(RemoteEventInterface $event): void
    {
        $this->logger->debug('Event', ['event' => [$event->getName(), $event->getData()]]);

        foreach ($this->subscribers as $subscriber) {
            foreach ($subscriber->getSupportedEvents() as $supportedEvent) {
                if ($supportedEvent === $event->getName() || $supportedEvent === '*') {
                    $subscriber->handleEvent($event);
                    break;
                }
            }
        }
    }

    private function onConnection(ConnectionInterface $connection): void
    {
        $this->logger->debug('Connected', ['remote' => $connection->getRemoteAddress()]);

        $connection = new EventBusConnection($connection);
        $connection->on('command', function (Command $command) use ($connection) {
            $this->handleRemoteCommand($connection, $command);
        });

        $this->registerEventSource(new RemoteEventSource($connection));
    }

    public function registerEventSource(EventSource $eventSource): void
    {
        $eventSource->subscribe(function (RemoteEventInterface $event) {
            $this->handleEvent($event);
        });
    }
}