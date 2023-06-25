<?php

namespace inisire\NetBus;

use inisire\NetBus\DTO\Query;
use inisire\NetBus\DTO\Result;
use inisire\NetBus\Query\ResultInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;

class QueryBusServer
{
    private SocketServer $server;

    /**
     * @var array<Connection>
     */
    private array $clients = [];

    /**
     * @var array<Buffer>
     */
    private array $buffers = [];

    /**
     * @var array<string, string>
     */
    private array $handlers = [];

    /**
     * @var array<string, string>
     */
    private array $waiting = [];

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
            var_dump($error);
        });
    }

    public function register(string $id, string $address, array $queries): void
    {
        $this->logger->debug('Register', ['id' => $id, 'address' => $address, 'queries' => $queries]);

        $this->handlers[$address] = $id;
    }

    public function handleData(string $id, string $data): void
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
                case 'register': {
                    $this->register($id, $command['d']['address'], $command['d']['queries'] ?? []);
                    break;
                }

                case 'query': {
                    $this->handleQuery($id, $command['d']['query_id'], $command['d']['address'], new Query($command['d']['name'], $command['d']['data']));
                    break;
                }

                case 'result': {
                    $this->handleResult($id, $command['d']['query_id'], new Result($command['d']['code'], $command['d']['data']));
                    break;
                }
            }
        }
    }

    private function handleQuery(string $clientId, string $queryId, string $address, QueryInterface $query): void
    {
        $this->logger->debug('Query', ['id' => $queryId, 'from' => $clientId, 'query' => [$query->getName(), $query->getData()]]);

        $handlerId = $this->handlers[$address] ?? null;
        $client = $this->clients[$clientId];

        if (!$handlerId) {
            $client->send(new Command(
                'result', [
                    'query_id' => $queryId,
                    'code' => 1,
                    'data' => ['error' => 'Not found']
                ]
            ));
        }

        $handlerConnection = $this->clients[$handlerId] ?? null;

        if (!$handlerConnection) {
            $client->send(new Command('result', [
                'query_id' => $queryId,
                'code' => 1,
                'data' => ['error' => 'Handler connection is broken']
            ]));
            return;
        }

        $handlerConnection->send(new Command('query', [
            'query_id' => $queryId,
            'name' => $query->getName(),
            'data' => $query->getData()
        ]));

        $this->waiting[$queryId] = $clientId;
    }

    private function handleResult(string $clientId, string $queryId, ResultInterface $result): void
    {
        $this->logger->debug('Result', ['id' => $queryId, 'from' => $clientId, 'result' => [$result->getCode(), $result->getData()]]);

        $waitingClientId = $this->waiting[$queryId] ?? null;
        $waitingClient = $this->clients[$waitingClientId] ?? null;

        if (!$waitingClientId || !$waitingClient) {
            $this->logger->debug('No result handler');
        }

        $waitingClient->send(new Command('result', [
            'query_id' => $queryId,
            'code' => $result->getCode(),
            'data' => $result->getData()
        ]));

        unset($this->waiting[$queryId]);
    }

    private function onEnd(string $id): void
    {
        unset($this->clients[$id]);
    }

    private function onConnection(ConnectionInterface $connection): void
    {
        $this->logger->debug('Connected', ['remote' => $connection->getRemoteAddress()]);

        $id = spl_object_id($connection);
        $this->clients[$id] = new Connection($connection);

        $connection->on('data', function (string $data) use ($id) {
            $this->handleData($id, $data);
        });

        $connection->on('end', function () use ($id) {
            $this->onEnd($id);
        });
    }
}