<?php

namespace inisire\NetBus\Query;

use React\Promise\PromiseInterface;

interface QueryBusInterface
{
    /**
     * @return PromiseInterface<ResultInterface>
     */
    public function execute(string $destination, QueryInterface $query): PromiseInterface;

    public function registerHandler(string $address, QueryHandler $handler): void;
}