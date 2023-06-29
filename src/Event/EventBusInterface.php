<?php

namespace inisire\NetBus\Event;

interface EventBusInterface
{
    public function dispatch(string $sourceId, EventInterface $event): void;
}