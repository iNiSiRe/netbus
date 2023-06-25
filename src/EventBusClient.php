<?php

namespace inisire\NetBus;

use inisire\NetBus\DTO\Query;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;

class EventBusClient implements EventDispatcherInterface
{
    private ?Connection $connection = null;

    private \SplQueue $queue;

    private Buffer $buffer;

    /**
     * @var array<EventSubscriber>
     */
    private array $subscribers = [];

    public function __construct(
        private LoopInterface $loop,
        private LoggerInterface $logger
    )
    {
        $this->queue = new \SplQueue();
        $this->buffer = new Buffer();
    }

    public function connect(string $host, ?string $address = null): PromiseInterface
    {
        $address ??= uniqid();

        $connector = new \React\Socket\Connector($this->loop);

        return $connector
            ->connect($host)
            ->then(function (\React\Socket\ConnectionInterface $connection) use ($address) {
                $connection->on('data', function (string $data) {
                    $this->handleData($data);
                });

                $connection->on('end', function () {
                    $this->handleDisconnect();
                });

                $this->connection = new Connection($connection);

                $this->sendCommand(new Command('auth', ['address' => $address]));

                while ($this->queue->count() > 0) {
                    $this->sendCommand($this->queue->pop());
                }

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
        $this->sendCommand(new Command('register', [
            'events' => $subscriber->getSupportedEvents()
        ]));
        $this->subscribers[] = $subscriber;
    }

    private function sendCommand(Command $command): void
    {
        if ($this->connection) {
            $this->connection->send($command);
        } else {
            $this->logger->debug('No connection. Command queued', [$command->getName(), $command->getData()]);
            $this->queue->push($command);
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