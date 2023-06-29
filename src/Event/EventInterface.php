<?php

namespace inisire\NetBus\Event;

interface EventInterface
{
    public function getName(): string;

    public function getData(): array;
}