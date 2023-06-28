<?php

namespace inisire\NetBus;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use React\Socket\ConnectionInterface;

class Connection implements EventEmitterInterface
{
    use EventEmitterTrait;

    private string $id;

    private ?Buffer $input;

    public function __construct(
        private readonly ?ConnectionInterface $connection,
    )
    {
        $this->id = uniqid();
        $this->input = new Buffer();

        $connection->on('end', function (string $data) {
            $this->connection = null;
            $this->emit('end', [$data]);
        });

        $this->

        $connection->on('data', function (string $data) {
            $this->input->write($data);

            foreach ($this->input->consume() as $chunk) {
                $command = json_decode($chunk, true);
                $this->emit('command', [new Command($command['x'], $command['d'])]);
            }
        });
    }

    public function send(Command $command): void
    {
        if (!$this->connection) {
            return;
        }

        $serialized = json_encode([
            'x' => $command->getName(),
            'd' => $command->getData()
        ]);

        $this->connection->write($serialized . PHP_EOL);
    }

    public function isConnected(): bool
    {
        return $this->connection !== null;
    }

    public function getId(): string
    {
        return $this->id;
    }
}