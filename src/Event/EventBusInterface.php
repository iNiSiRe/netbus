<?php

namespace inisire\NetBus\Event;

interface EventBusInterface
{
    public function createSource(string $sourceId): EventSourceInterface;

    public function subscribe(string $sourceId, EventSubscriber $subscriber): void;

    public function dispatch(string $sourceId, EventInterface $event): void;
}