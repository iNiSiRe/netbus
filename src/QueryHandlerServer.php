<?php

namespace inisire\NetBus;

use inisire\NetBus\DTO\Query;
use inisire\NetBus\DTO\Result;
use inisire\NetBus\Query\ResultInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;
use function React\Promise\resolve;

class QueryHandlerServer
{
    private SocketServer $server;

    /**
     * @var array<Connection>
     */
    private array $connections = [];

    /**
     * @var array<Buffer>
     */
    private array $buffers = [];

    /**
     * @var array<QueryHandler>
     */
    private array $handlers = [];

    public function __construct(
        private readonly LoopInterface $loop,
        private readonly LoggerInterface $logger
    )
    {
    }

    public function start(string $address)
    {
        $this->server = new SocketServer($address, [], $this->loop);
        $this->server->on('connection', function (ConnectionInterface $connection) {
            $this->onConnection($connection);
        });
        $this->server->on('error', function ($error) {
            $this->logger->error('Connection error', ['error' => serialize($error)]);
        });
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
                case 'query': {
                    $this->handleQuery($id, $command['d']['query_id'], new Query($command['d']['name'], $command['d']['data']));
                    break;
                }
            }
        }
    }

    private function handleQuery(string $clientId, string $queryId, QueryInterface $query): void
    {
        $this->logger->debug('Query', ['id' => $queryId, 'from' => $clientId, 'query' => [$query->getName(), $query->getData()]]);

        $client = $this->connections[$clientId] ?? null;

        $handler = null;

        foreach ($this->handlers as $h) {
           if (in_array($query->getName(), $h->getSupportedQueries())) {
               $handler = $h;
               break;
           }
        }

        if ($handler) {
            $result = resolve($handler->handleQuery($query));
            $result->then(function (ResultInterface $result) use ($client, $queryId) {
                $client->send(new Command('result', [
                    'query_id' => $queryId,
                    'code' => $result->getCode(),
                    'data' => $result->getData()
                ]));
            });
        } else {
            $client->send(new Command('result', [
                'query_id' => $queryId,
                'code' => -1,
                'data' => ['error' => 'Not found']
            ]));
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
        $this->connections[$id] = new Connection($connection);

        $connection->on('data', function (string $data) use ($id) {
            $this->handleData($id, $data);
        });

        $connection->on('end', function () use ($id) {
            $this->onEnd($id);
        });
    }

    public function registerHandler(QueryHandler $handler): void
    {
        $this->handlers[] = $handler;
    }
}