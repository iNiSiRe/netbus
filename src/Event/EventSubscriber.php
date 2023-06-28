<?php

namespace inisire\NetBus\Event;

interface EventSubscriber
{
    public function getSupportedEvents(): array;

    public function handleEvent(Event $event);
}