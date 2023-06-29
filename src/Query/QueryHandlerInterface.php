<?php

namespace inisire\NetBus\Query;

use React\Promise\PromiseInterface;

interface QueryHandlerInterface
{
    public function getSupportedQueries(): array;

    /**
     * @return PromiseInterface<ResultInterface>
     */
    public function handleQuery(QueryInterface $query): PromiseInterface;
}