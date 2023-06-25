<?php

namespace inisire\NetBus;

use React\Promise\PromiseInterface;

interface QueryBus
{
    public function execute(QueryInterface $query): PromiseInterface;
}