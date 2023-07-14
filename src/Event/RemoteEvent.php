<?php

namespace inisire\NetBus\Event;

class RemoteEvent implements EventInterface
{
    public function __construct(
        private readonly string $sourceId,
        private readonly string $name,
        private readonly array  $data,
    )
    {
    }

    public function getSourceId(): string
    {
        return $this->sourceId;
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