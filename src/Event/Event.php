<?php

namespace inisire\NetBus\Event;

interface Event
{
    public function getName(): string;

    public function getData(): array;
}