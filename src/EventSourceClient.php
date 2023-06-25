<?php

namespace inisire\NetBus;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;

class EventSourceClient
{
    private ?Connection $connection = null;
    private ?Buffer $buffer = null;

    /**
     * @var array<EventSubscriber>
     */
    private array $subscribers = [];

    public function __construct(
        private readonly LoopInterface $loop,
        private readonly LoggerInterface $logger
    )
    {
    }

    public function connect(string $host): PromiseInterface
    {
        $connector = new \React\Socket\Connector($this->loop);

        return $connector
            ->connect($host)
            ->then(function (\React\Socket\ConnectionInterface $connection) {
                $connection->on('data', function (string $data) {
                    $this->handleData($data);
                });

                $connection->on('end', function () {
                    $this->handleDisconnect();
                });

                $this->buffer = new Buffer();
                $this->connection = new Connection($connection);

                return $connection;
            }, function (\Exception $e) {
                $this->logger->error('Connection error', ['error' => $e->getMessage()]);
                return null;
            })
            ->otherwise(function ($error) {
                var_dump($error);
            });
    }

    private function handleDisconnect(): void
    {
        $this->logger->info('Disconnect');
        $this->connection = null;
        $this->buffer = null;
    }

    private function handleData(string $data): void
    {
        $this->buffer->write($data);

        foreach ($this->buffer->consume() as $chunk) {
            $command = json_decode($chunk, true);

            switch ($command['x']) {
                case 'event': {
                    $this->handleEvent(new \inisire\NetBus\DTO\Event($command['d']['name'], $command['d']['data']));
                    break;
                }
            }
        }
    }

    private function handleEvent(Event $event): void
    {
        foreach ($this->subscribers as $subscriber) {
            if (in_array($event->getName(), $subscriber->getSupportedEvents())) {
                $subscriber->handleEvent($event);
            }
        }
    }

    public function subscribe(EventSubscriber $subscriber): void
    {
        $this->subscribers[] = $subscriber;
    }
}