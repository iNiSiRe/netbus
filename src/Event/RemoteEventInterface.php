<?php

namespace inisire\NetBus\Event;

interface RemoteEventInterface extends Event
{
    public function getFrom(): string;
}