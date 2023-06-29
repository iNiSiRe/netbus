<?php

namespace inisire\NetBus\Query;

use inisire\NetBus\Command;
use inisire\NetBus\Connection;
use inisire\NetBus\Query\Query;
use inisire\NetBus\Query\Result;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;

class QueryBusClient implements QueryBusInterface
{
    private ?Connection $connection = null;

    /**
     * @var \SplQueue<Command>
     */
    private \SplQueue $queue;

    /**
     * @var QueryHandlerInterface[]
     */
    private array $handlers = [];

    /**
     * @var Deferred[]
     */
    private array $waiting = [];

    public function __construct(
        private LoopInterface   $loop,
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

                $this->connection->on('command', function (Command $command) {
                    $this->handleRemoteCommand($command);
                });

                $this->connection->on('end', function () {
                    $this->handleDisconnect();
                });

                while ($this->queue->count() > 0) {
                    $command = $this->queue->pop();
                    $this->logger->debug('Connection established. Resend command', [$command->getName(), $command->getData()]);
                    $this->connection->send($command);
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

    private function handleRemoteCommand(Command $command): void
    {
        $this->logger->debug('Received command', ['name' => $command->getName(), 'data' => $command->getData()]);

        $data = $command->getData();

        switch ($command->getName()) {
            case 'query':
            {
                $this->handleRemoteQuery(new Query($data['name'], $data['data'], $data['id']));
                break;
            }

            case 'result':
            {
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

    public function registerHandler(string $nodeId, QueryHandlerInterface $handler): void
    {
        $this->send(new Command('register', ['nodeId' => $nodeId, 'queries' => $handler->getSupportedQueries()]));

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

    public function execute(string $nodeId, QueryInterface $query): PromiseInterface
    {
        $deferred = new Deferred();

        $queryId = uniqid();

        $this->send(new Command('query', [
            'id' => $queryId,
            'destination' => $nodeId,
            'name' => $query->getName(),
            'data' => $query->getData()
        ]));

        $this->waiting[$queryId] = $deferred;

        return $deferred->promise();
    }
}