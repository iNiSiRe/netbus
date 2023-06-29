<?php

namespace inisire\NetBus\Event;

use inisire\NetBus\Event\RemoteEventInterface;

class RemoteEvent implements RemoteEventInterface
{
    public function __construct(
        private readonly string $sourceNodeId,
        private readonly string $name,
        private readonly array  $data,
    )
    {
    }

    public function getSourceNodeId(): string
    {
        return $this->sourceNodeId;
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