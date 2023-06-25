<?php

namespace inisire\NetBus;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;

class EventSourceServer implements EventDispatcherInterface
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

    private function onEnd(string $id): void
    {
        unset($this->connections[$id]);
    }

    private function onConnection(ConnectionInterface $connection): void
    {
        $this->logger->debug('Connected', ['remote' => $connection->getRemoteAddress()]);

        $id = spl_object_id($connection);
        $this->connections[$id] = new EventBusConnection($connection);

        $connection->on('end', function () use ($id) {
            $this->onEnd($id);
        });
    }

    private function sendCommand(Command $command): void
    {
        foreach ($this->connections as $connection) {
            $connection->send($command);
        }
    }

    public function dispatch(object $event)
    {
        if (!$event instanceof Event) {
            $this->logger->error('Bad event', ['class' => $event::class, 'expected' => Event::class]);
            return;
        }

        $this->sendCommand(new Command('event', ['name' => $event->getName(), 'data' => $event->getData()]));
    }
}