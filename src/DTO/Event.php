<?php

namespace inisire\NetBus\DTO;

class Event implements \inisire\NetBus\Event\Event
{
    public function __construct(
        private readonly string $name,
        private readonly array $data
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