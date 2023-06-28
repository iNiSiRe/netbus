<?php

namespace inisire\NetBus\Query;

interface QueryInterface
{
    public function getName(): string;

    public function getData(): array;
}