<?php

namespace inisire\NetBus\Event;

interface EventSourceInterface
{
    public function dispatch(EventInterface $event): void;
}