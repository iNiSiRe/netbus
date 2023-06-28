<?php

namespace inisire\NetBus\Query;

use inisire\NetBus\Command;
use inisire\NetBus\Connection;
use inisire\NetBus\DTO\Query;
use inisire\NetBus\DTO\Result;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;


class QueryBusClient implements QueryBusInterface
{
    private ?Connection $connection = null;
    private \SplQueue $queue;

    /**
     * @var QueryHandler[]
     */
    private array $handlers = [];

    /**
     * @var Deferred[]
     */
    private array $waiting = [];

    public function __construct(
        private LoopInterface $loop,
        private LoggerInterface $logger
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

                $connection->on('command', function (Command $command) {
                    $this->handleCommand($command);
                });

                $connection->on('end', function () {
                    $this->handleDisconnect();
                });

                while ($this->queue->count() > 0) {
                    $this->connection->send($this->queue->pop());
                }

                return $connection;
            }, function (\Exception $e) {
                $this->logger->error('Connection error', ['error' => $e->getMessage()]);
                return null;
            });
    }

    private function handleDisconnect(): void
    {
        $this->logger->info('Disconnect');
        $this->connection = null;
    }

    private function handleCommand(Command $command): void
    {
        $data = $command->getData();

        switch ($command->getName()) {
            case 'query': {
                $this->handleRemoteQuery(new Query($data['name'], $data['data'], $data['id']));
                break;
            }

            case 'result': {
                $this->handleRemoteResult($data['id'], new Result($data['code'], $data['data']));
                break;
            }
        }
    }

    private function handleRemoteResult(string $queryId, ResultInterface $result): void
    {
        $waiting = $this->waiting[$queryId] ?? null;

        if (!$waiting) {
            $this->logger->error('No waiting queries for coming result');
            return;
        }

        unset($this->waiting[$queryId]);

        $waiting->resolve($result);
    }

    private function handleRemoteQuery(QueryInterface $query): void
    {
        foreach ($this->handlers as $handler) {
            if (in_array($query->getName(), $handler->getSupportedQueries())) {
                $result = $handler->handleQuery($query);

                $result
                    ->then(
                        function (ResultInterface $result) {
                            return $result;
                        },
                        function (\Throwable $e) {
                            return new Result(-1, [
                                'error' => [
                                    'exception' => $e::class,
                                    'message' => $e->getMessage()
                                ]
                            ]);
                        }
                    )
                    ->then(function (ResultInterface $result) use ($query) {
                        $this->send(new Command('result', [
                            'id' => $query->getId(),
                            'code' => $result->getCode(),
                            'data' => $result->getData()
                        ]));
                    });

                break;
            }
        }
    }

    public function registerHandler(string $address, QueryHandler $handler): void
    {
        $this->send(new Command('register', ['address' => $address, 'queries' => $handler->getSupportedQueries()]));

        $this->handlers[] = $handler;
    }

    private function send(Command $command): void
    {
        if ($this->connection) {
            $this->connection->send($command);
        } else {
            $this->logger->debug('No connection. Command queued', [$command->getName(), $command->getData()]);
            $this->queue->push($command);
        }
    }

    public function execute(string $destination, QueryInterface $query): PromiseInterface
    {
        $deferred = new Deferred();

        $queryId = uniqid();

        $this->send(new Command('query', [
            'query_id' => $queryId,
            'dst' => $deferred,
            'name' => $query->getName(),
            'data' => $query->getData()
        ]));

        $this->waiting[$queryId] = $deferred;

        return $deferred->promise();
    }
}