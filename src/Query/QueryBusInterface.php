<?php

namespace inisire\NetBus\Query;

use React\Promise\PromiseInterface;

interface QueryBusInterface
{
    /**
     * @return PromiseInterface<ResultInterface>
     */
    public function execute(string $nodeId, QueryInterface $query): PromiseInterface;

    public function registerHandler(string $nodeId, QueryHandlerInterface $handler): void;
}