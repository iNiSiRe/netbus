<?php

namespace inisire\NetBus\Query;

use React\Promise\PromiseInterface;

interface QueryBus
{
    /**
     * @return PromiseInterface<ResultInterface>
     */
    public function execute(string $destination, QueryInterface $query): PromiseInterface;
}