<?php

namespace inisire\NetBus\Event;

class Event implements \inisire\NetBus\Event\EventInterface
{
    public function __construct(
        private readonly string $name,
        private readonly array $data,
    )
    {
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