<?php

namespace inisire\NetBus\Event;

interface EventSubscriber
{
    /**
     * @return array<SubscriptionInterface>
     */
    public function getEventSubscriptions(): array;
}