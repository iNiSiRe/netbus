<?php

namespace inisire\NetBus\Query;

interface QueryBusInterface
{
    public function execute(string $destinationId, string $name, array $data = []): ResultInterface;
}