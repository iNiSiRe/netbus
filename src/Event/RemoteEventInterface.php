<?php

namespace inisire\NetBus\Event;

interface RemoteEventInterface
{
    public function getSourceNodeId(): string;

    public function getName(): string;

    public function getData(): array;
}