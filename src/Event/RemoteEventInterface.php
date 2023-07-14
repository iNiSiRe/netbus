<?php

namespace inisire\NetBus\Event;

interface RemoteEventInterface
{
    public function getSourceId(): string;

    public function getName(): string;

    public function getData(): array;
}