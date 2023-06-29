<?php

namespace inisire\NetBus\Query\Server;

use inisire\NetBus\Command;
use inisire\NetBus\Connection;
use inisire\NetBus\DTO\Query;
use inisire\NetBus\DTO\Result;
use inisire\NetBus\Query\QueryHandler;
use inisire\NetBus\Query\ResultInterface;
use inisire\NetBus\Query\QueryInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;
use function React\Promise\resolve;

class QueryBus implements \inisire\NetBus\Query\QueryBusInterface
{
    private SocketServer $server;

    /**
     * @var array<string, QueryHandler>
     */
    private array $handlers = [];

    public function __construct(
        private readonly LoopInterface   $loop,
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
            $this->logger->error('Server error', [serialize($error)]);
        });
    }

    private function handleRemoteRegister(Connection $from, Command $command): void
    {
        $data = $command->getData();
        $address = $data['address'];
        $queries = $data['queries'] ?? [];

        $this->handlers[$address] = new RemoteQueryHandler($this->loop, $from, $queries);

        $from->on('end', function () use ($address) {
            unset($this->handlers[$address]);
        });
    }

    private function handleRemoteCommand(Connection $from, Command $command): void
    {
        switch ($command->getName()) {
            case 'query':
            {
                $data = $command->getData();
                $this->handleRemoteQuery($from, $data['address'], new Query($data['name'], $data['data'], $data['id']));
                break;
            }

            case 'register':
            {
                $this->handleRemoteRegister($from, $command);
                break;
            }
        }
    }

    private function handleRemoteQuery(Connection $from, string $address, QueryInterface $query): void
    {
        $this->logger->debug('Handle remote query', ['query' => [$query->getName(), $query->getData()]]);

        $this
            ->execute($address, $query)
            ->then(function (ResultInterface $result) use ($from, $query) {
                $this->logger->debug('Remote query: Send result', ['code' => $result->getCode(), 'data' => $result->getData()]);
                $from->send(new Command('result', [
                    'id' => $query->getId(),
                    'code' => $result->getCode(),
                    'data' => $result->getData()
                ]));
            });
    }

    private function onDisconnect(Connection $from): void
    {
        $this->logger->debug('Client disconnected');
    }

    private function onConnection(ConnectionInterface $connection): void
    {
        $this->logger->debug('Client connected', ['remote' => $connection->getRemoteAddress()]);

        $busConnection = new Connection($connection);

        $busConnection->on('command', function (Command $command) use ($busConnection) {
            $this->handleRemoteCommand($busConnection, $command);
        });

        $busConnection->on('end', function () use ($busConnection) {
            $this->onDisconnect($busConnection);
        });
    }

    public function registerHandler(string $address, QueryHandler $handler): void
    {
        $this->handlers[$address] = $handler;
    }

    public function execute(string $destination, QueryInterface $query): PromiseInterface
    {
        $handler = $this->handlers[$destination] ?? null;

        if ($handler) {
            $result = $handler->handleQuery($query);
        } else {
            $result = resolve(new Result(-1, ['error' => 'Not found']));
        }

        return $result;
    }
}