<?php

namespace inisire\NetBus\Query\Server;

use inisire\NetBus\Command;
use inisire\NetBus\Connection;
use inisire\NetBus\DTO\Result;
use inisire\NetBus\Query\QueryHandler;
use inisire\NetBus\Query\QueryInterface;
use inisire\NetBus\Query\ResultInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;

class RemoteQueryHandler implements QueryHandler
{
    /**
     * @var array<string, Deferred>
     */
    private array $waiting = [];

    public function __construct(
        private readonly LoopInterface $loop,
        private readonly Connection $connection,
        private readonly array $queries = []
    )
    {
        $this->connection->on('command', function (Command $command) {
            $data = $command->getData();
            switch ($command->getName()) {
                case 'result': {
                    $this->handleResult($data['id'], new Result($data['code'], $data['data']));
                    break;
                }
            }
        });
    }

    public function getSupportedQueries(): array
    {
        return $this->queries;
    }

    public function handleQuery(QueryInterface $query): PromiseInterface
    {
        if (!$this->connection->isConnected()) {
            return resolve(new Result(-1, ['error' => 'Connection is broken']));
        }

        $this->connection->send(new Command('query', [
            'id' => $query->getId(),
            'name' => $query->getName(),
            'data' => $query->getData()
        ]));

        $deferred = new Deferred();
        $this->waiting[$query->getId()] = $deferred;

        $this->loop->addTimer(5, function () use ($query, $deferred) {
            $deferred->resolve(new Result(-1, ['error' => 'Timeout']));
        });

        return $deferred->promise();
    }

    public function handleResult(string $queryId, ResultInterface $result): void
    {
        $waiting = $this->waiting[$queryId] ?? null;

        if (!$waiting) {
            return;
        }

        unset($this->waiting[$queryId]);
        $waiting->resolve($result);
    }
}