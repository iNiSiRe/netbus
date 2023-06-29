<?php

namespace inisire\NetBus\Event;

use inisire\NetBus\Command;
use inisire\NetBus\Connection;
use inisire\NetBus\Event\Server\EventSource;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

class EventBusClient
{
    private ?Connection $connection = null;

    private \SplQueue $queue;

    /**
     * @var array<EventSubscriber>
     */
    private array $subscribers = [];

    public function __construct(
        private readonly LoopInterface $loop,
        private readonly LoggerInterface $logger,
    )
    {
        $this->queue = new \SplQueue();
    }

    public function connect(string $host): PromiseInterface
    {
        $connector = new \React\Socket\Connector($this->loop);

        return $connector
            ->connect($host)
            ->then(function (\React\Socket\ConnectionInterface $connection) {
                $this->connection = new Connection($connection);

                $this->connection->on('command', function (Command $command) {
                    $this->handleRemoteCommand($command);
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

    private function handleRemoteCommand(Command $command): void
    {
        switch ($command->getName()) {
            case 'event': {
                $data = $command->getData();
                $this->handleRemoteEvent(new \inisire\NetBus\DTO\RemoteEvent($data['from'], $data['name'], $data['data']));
                break;
            }
        }
    }

    private function handleRemoteEvent(RemoteEventInterface $event): void
    {
        $this->logger->debug('Event received', ['from' => $event->getFrom(), 'name' => $event->getName(), 'data' => $event->getData()]);

        foreach ($this->subscribers as $subscriber) {
            foreach ($subscriber->getSupportedEvents() as $supportedEvent) {
                if ($supportedEvent === $event->getName() || $supportedEvent === '*') {
                    $subscriber->handleEvent($event);
                    break;
                }
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

    private function registerEventSource(EventSource $eventSource)
    {
        $eventSource->subscribe(function (RemoteEventInterface $event) {
            $this->sendCommand(new Command('event', [
                'from' => $event->getFrom(),
                'name' => $event->getName(),
                'data' => $event->getData()
            ]));
        });
    }

    public function createEventDispatcher(string $address): EventDispatcherInterface
    {
        $source = new InternalEventSource($address);
        $this->registerEventSource($source);

        return $source;
    }
}