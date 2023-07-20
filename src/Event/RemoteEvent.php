<?php

namespace inisire\NetBus\Event;

class RemoteEvent implements RemoteEventInterface
{
    public function __construct(
        private readonly string $source,
        private readonly string $name,
        private readonly array  $data,
    )
    {
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public  function getName(): string
    {
        return $this->name;
    }

    public function getData(): array
    {
        return $this->data;
    }
}