<?php

namespace inisire\NetBus\Query;

class Route
{
    public function __construct(
        private readonly string $busId,
        private readonly string $queryName
    )
    {
    }

    public function getBusId(): string
    {
        return $this->busId;
    }

    public function getQueryName(): string
    {
        return $this->queryName;
    }
}