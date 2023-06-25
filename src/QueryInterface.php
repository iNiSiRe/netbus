<?php

namespace inisire\NetBus;

interface QueryInterface
{
    public function getName(): string;

    public function getData(): array;
}