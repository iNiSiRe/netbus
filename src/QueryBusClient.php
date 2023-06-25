<?php

namespace inisire\NetBus;

use inisire\NetBus\DTO\Query;
use inisire\NetBus\DTO\Result;
use inisire\NetBus\Query\ResultInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;


class QueryBusClient implements QueryBus
{
    private ?Connection $connection = null;
    private \SplQueue $queue;
    private Buffer $buffer;

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
        $this->buffer = new Buffer();
    }

    public function connect(string $host, ?string $busAddress = null): PromiseInterface
    {
        $busAddress ??= uniqid();
        $connector = new \React\Socket\Connector($this->loop);

        return $connector
            ->connect($host)
            ->then(function (\React\Socket\ConnectionInterface $connection) use ($busAddress) {
                $connection->on('data', function (string $data) {
                    $this->handleData($data);
                });
                $connection->on('end', function () {
                    $this->handleDisconnect();
                });

                $this->connection = new Connection($connection);
                $this->buffer = new Buffer();

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

    private function handleData(string $data): void
    {
        $this->buffer->write($data);

        foreach ($this->buffer->consume() as $chunk) {
            $command = json_decode($chunk, true);

            switch ($command['x']) {
                case 'query': {
                    $this->handleQuery($command['d']['query_id'], new Query($command['d']['name'], $command['d']['data']));
                    break;
                }

                case 'result': {
                    $this->handleResult($command['d']['query_id'], new Result($command['d']['code'], $command['d']['data']));
                    break;
                }
            }
        }
    }

    private function handleResult(string $queryId, ResultInterface $result): void
    {
        $waiting = $this->waiting[$queryId] ?? null;

        if (!$waiting) {
            $this->logger->error('No waiting queries for coming result');
            return;
        }

        unset($this->waiting[$queryId]);

        $waiting->resolve($result);
    }

    private function handleQuery(string $queryId, QueryInterface $query): void
    {
        foreach ($this->handlers as $handler) {
            if (in_array($query->getName(), $handler->getSupportedQueries())) {
                try {
                    $result = $handler->handleQuery($query);
                } catch (\Throwable $e) {
                    $result = new Result(-1, [
                        'error' => [
                            'exception' => $e::class,
                            'message' => $e->getMessage()
                        ]
                    ]);
                }

                $this->send(new Command('result', [
                    'query_id' => $queryId,
                    'code' => $result->getCode(),
                    'data' => $result->getData()
                ]));

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

    public function execute(string $address, QueryInterface $query): PromiseInterface
    {
        $deferred = new Deferred();

        $queryId = uniqid();

        $this->send(new Command('query', [
            'query_id' => $queryId,
            'address' => $address,
            'name' => $query->getName(),
            'data' => $query->getData()
        ]));

        $this->waiting[$queryId] = $deferred;

        return $deferred->promise();
    }
}