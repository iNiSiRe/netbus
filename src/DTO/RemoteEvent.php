<?php

namespace inisire\NetBus\DTO;

use inisire\NetBus\Event\RemoteEventInterface;

class RemoteEvent implements RemoteEventInterface
{
    public function __construct(
        private readonly string $from,
        private readonly string $name,
        private readonly array $data,
    )
    {
    }

    public function getFrom(): string
    {
        return $this->from;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getData(): array
    {
        return $this->data;
    }
}