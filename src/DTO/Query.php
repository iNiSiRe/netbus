<?php

namespace inisire\NetBus\DTO;

class Query implements \inisire\NetBus\Query\QueryInterface
{
    private readonly string $id;

    public function __construct(
        private readonly string $name,
        private readonly array  $data,
        ?string $id = null,
    )
    {
        $this->id = $id ?? uniqid();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getId(): string
    {
        return $this->id;
    }
}