<?php

namespace inisire\NetBus;

use inisire\NetBus\DTO\Query;
use inisire\NetBus\DTO\Result;
use inisire\NetBus\Query\ResultInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;

class EventBusServer
{
    private SocketServer $server;

    /**
     * @var array<EventBusConnection>
     */
    private array $connections = [];

    /**
     * @var array<Buffer>
     */
    private array $buffers = [];

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

    public function register(string $id, array $events): void
    {
        $this->logger->debug('Register', ['id' => $id, 'events' => $events]);

        $connection = $this->connections[$id];
        $connection->subscribe($events);
    }

    public function auth(string $id, string $address): void
    {
        $this->logger->debug('Auth', ['id' => $id, 'address' => $address]);

        $connection = $this->connections[$id];

        $connection->assignAddress($address);
    }

    private function handleData(string $id, string $data): void
    {
        $buffer = $this->buffers[$id] ?? null;

        if (!$buffer) {
            $buffer = new Buffer();
            $this->buffers[$id] = $buffer;
        }

        $buffer->write($data);

        foreach ($buffer->consume() as $chunk) {
            $command = json_decode($chunk, true);

            switch ($command['x']) {
                case 'auth': {
                    $this->auth($id, $command['d']['address']);
                    break;
                }

                case 'register': {
                    $this->register($id, $command['d']['events'] ?? []);
                    break;
                }

                case 'event': {
                    $this->handleEvent($id, new \inisire\NetBus\DTO\Event($command['d']['name'], $command['d']['data']));
                    break;
                }

                default: {

                }
            }
        }
    }

    private function handleEvent(string $clientId, Event $event): void
    {
        $this->logger->debug('Event', ['from' => $clientId, 'event' => [$event->getName(), $event->getData()]]);

        foreach ($this->connections as $connection) {
            if (in_array($event->getName(), $connection->getSubscribedEvents())) {
                $connection->send(new Command('event', [
                    'name' => $event->getName(),
                    'data' => $event->getData()
                ]));
            }
        }
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

        $connection->on('data', function (string $data) use ($id) {
            $this->handleData($id, $data);
        });

        $connection->on('end', function () use ($id) {
            $this->onEnd($id);
        });
    }
}