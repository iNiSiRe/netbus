<?php

namespace inisire\NetBus\Query\Server;

use inisire\NetBus\Command;
use inisire\NetBus\DTO\Query;
use inisire\NetBus\DTO\Result;
use inisire\NetBus\Query\ResultInterface;
use inisire\NetBus\Query\QueryInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;

class QueryBus
{
    private SocketServer $server;

    /**
     * @var \SplObjectStorage<QueryBusConnection>
     */
    private \SplObjectStorage $connections;

    /**
     * @var array<string, QueryBusConnection>
     */
    private array $waiting = [];

    /**
     * @var array<string, QueryBusConnection>
     */
    private array $handlers = [];

    public function __construct(
        private readonly LoopInterface   $loop,
        private readonly LoggerInterface $logger
    )
    {
        $this->connections = new \SplObjectStorage();
    }

    public function start(string $address)
    {
        $this->server = new SocketServer($address, [], $this->loop);

        $this->server->on('connection', function (ConnectionInterface $connection) {
            $this->onConnection($connection);
        });

        $this->server->on('error', function ($error) {
            $this->logger->error('Server error', [serialize($error)]);
        });
    }

    private function handleRegister(QueryBusConnection $from, Command $command): void
    {
        $data = $command->getData();
        $address = $data['address'];

        $from->assignAddress($address);
        $this->handlers[$address] = $from;
    }

    private function handleCommand(QueryBusConnection $from, Command $command): void
    {
        if (!$from->isConnected()) {
            return;
        }

        $data = $command['d'];
        switch ($command->getName()) {
            case 'query':
            {
                $this->handleQuery($from, $data['query_id'], $data['address'], new Query($data['name'], $data['data']));
                break;
            }

            case 'result':
            {
                $this->handleResult($data['query_id'], new Result($data['code'], $data['data']));
                break;
            }

            case 'register':
            {
                $this->handleRegister($from, $command);
                break;
            }
        }
    }

    private function handleQuery(QueryBusConnection $from, string $queryId, string $address, QueryInterface $query): void
    {
        $this->logger->debug('Query', ['id' => $queryId, 'from' => $from->getAddress(), 'query' => [$query->getName(), $query->getData()]]);

        $handler = $this->handlers[$address] ?? null;

        if (!$handler || !$handler->isConnected()) {
            $from->send(new Command(
                'result', [
                    'query_id' => $queryId,
                    'code' => -1,
                    'data' => ['error' => 'Address not registered']
                ]
            ));
        }

        $handler->send(new Command('query', [
            'query_id' => $queryId,
            'name' => $query->getName(),
            'data' => $query->getData()
        ]));

        $this->waiting[$queryId] = $from;

        $this->loop->addTimer(5, function () use ($queryId) {
            $waiting = $this->waiting[$queryId] ?? null;
            if ($waiting) {
                $this->handleResult($queryId, new Result(-1, ['error' => 'Timeout']));
            }
        });
    }

    private function handleResult(string $queryId, ResultInterface $result): void
    {
        $this->logger->debug('Handle result', ['id' => $queryId, 'result' => [$result->getCode(), $result->getData()]]);

        $dst = $this->waiting[$queryId] ?? null;

        if (!$dst || !$dst->isConnected()) {
            $this->logger->debug('No result handler');
        }

        $dst->send(new Command('result', [
            'query_id' => $queryId,
            'code' => $result->getCode(),
            'data' => $result->getData()
        ]));

        unset($this->waiting[$queryId]);
    }

    private function onEnd(QueryBusConnection $from): void
    {
        $this->logger->debug('Client disconnected', ['address' => $from->getAddress()]);

        $this->connections->detach($from);

        if ($from->getAddress() !== null) {
            unset($this->handlers[$from->getAddress()]);
        }
    }

    private function onConnection(ConnectionInterface $connection): void
    {
        $this->logger->debug('Client connected', ['remote' => $connection->getRemoteAddress()]);

        $busConnection = new QueryBusConnection($connection);
        $this->connections->attach($busConnection);

        $connection->on('command', function (Command $command) use ($busConnection) {
            $this->handleCommand($busConnection, $command);
        });

        $connection->on('end', function () use ($busConnection) {
            $this->onEnd($busConnection);
        });
    }
}