<?php

namespace inisire\NetBus;

use React\Promise\PromiseInterface;

interface QueryHandler
{
    public function getSupportedQueries(): array;

    public function handleQuery(QueryInterface $query): Query\ResultInterface|PromiseInterface;
}