<?php

namespace inisire\NetBus\Query;


class Result implements ResultInterface
{
    public function __construct(
        private readonly int $code,
        private readonly array $data
    )
    {
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function getData(): array
    {
        return $this->data;
    }
}