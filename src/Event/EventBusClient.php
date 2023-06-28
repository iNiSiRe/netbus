<?php

namespace inisire\NetBus\Event;

use inisire\NetBus\Command;
use inisire\NetBus\Connection;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

class EventBusClient implements EventDispatcherInterface
{
    private ?Connection $connection = null;

    private \SplQueue $queue;

    /**
     * @var array<EventSubscriber>
     */
    private array $subscribers = [];

    public function __construct(
        private readonly LoopInterface $loop,
        private readonly LoggerInterface $logger
    )
    {
        $this->queue = new \SplQueue();
    }

    public function connect(string $host, ?string $address = null): PromiseInterface
    {
        $connector = new \React\Socket\Connector($this->loop);

        return $connector
            ->connect($host)
            ->then(function (\React\Socket\ConnectionInterface $connection) use ($address) {
                $this->connection = new Connection($connection);

                $this->connection->on('command', function (Command $command) {
                    $this->handleCommand($command);
                });

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

    private function handleCommand(Command $command): void
    {
        switch ($command->getName()) {
            case 'event': {
                $data = $command->getData();
                $this->handleEvent(new \inisire\NetBus\DTO\Event($data['name'], $data['data']));
                break;
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
        $this->sendCommand(new Command('subscribe', ['events' => $subscriber->getSupportedEvents()]));
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