<?php

namespace inisire\NetBus\Query;

interface QueryBusInterface
{
    public function execute(string $destinationId, string $name, array $data = []): ResultInterface;

    public function registerHandler(string $busId, QueryHandlerInterface $handler): void;

    public function on(Route $route, callable $handler): void;
}