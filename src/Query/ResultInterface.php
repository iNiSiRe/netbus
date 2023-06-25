<?php

namespace inisire\NetBus\Query;

interface ResultInterface
{
    public function getCode(): int;

    public function getData(): array;
}