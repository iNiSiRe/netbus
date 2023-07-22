<?php

namespace inisire\NetBus\Event;

interface EventBusInterface
{
    public function createSource(string $source): EventSourceInterface;

    public function registerSubscriber(EventSubscriber $subscriber): void;

    public function subscribe(SubscriptionInterface $subscription): void;

    public function dispatch(string $source, EventInterface $event): void;
}