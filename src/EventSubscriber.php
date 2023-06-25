<?php

namespace inisire\NetBus;

interface EventSubscriber
{
    public function getSupportedEvents(): array;

    public function handleEvent(Event $event);
}