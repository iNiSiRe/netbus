<?php

namespace inisire\NetBus\Event;

interface EventSubscriber
{
    public function getSubscribedEvents(): array;
}