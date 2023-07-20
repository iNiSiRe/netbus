<?php

namespace inisire\NetBus\Event;

use inisire\NetBus\Event\EventInterface;
use inisire\NetBus\Event\Subscription\MatcherInterface;

interface SubscriptionInterface
{
    public function getSubscribedSources(): MatcherInterface;

    public function getSubscribedEvents(): MatcherInterface;

    public function handleEvent(EventInterface $event): void;
}