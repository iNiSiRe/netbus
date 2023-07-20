<?php

namespace inisire\NetBus\Event;

use inisire\NetBus\Event\Subscription\MatcherInterface;

class CallableSubscription implements SubscriptionInterface
{
    private readonly \Closure $handler;

    public function __construct(
        private readonly MatcherInterface $sources,
        private readonly MatcherInterface $events,
        callable                          $handler
    )
    {
        $this->handler = \Closure::fromCallable($handler);
    }

    public function getSubscribedSources(): MatcherInterface
    {
        return $this->sources;
    }

    public function getSubscribedEvents(): MatcherInterface
    {
        return $this->events;
    }

    public function handleEvent(EventInterface $event): void
    {
        call_user_func($this->handler, $event);
    }
}