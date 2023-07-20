<?php

namespace inisire\NetBus\Event;

interface EventBusInterface
{
    public function createSource(string $source): EventSourceInterface;

    public function subscribe(EventSubscriber $subscriber): void;

    public function dispatch(string $source, EventInterface $event): void;
}