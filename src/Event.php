<?php

namespace inisire\NetBus;

interface Event
{
    public function getName(): string;

    public function getData(): array;
}