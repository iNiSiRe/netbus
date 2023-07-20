<?php

namespace inisire\NetBus\Event;

interface RemoteEventInterface extends EventInterface
{
    public function getSource(): string;
}