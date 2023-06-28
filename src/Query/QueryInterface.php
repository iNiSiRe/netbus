<?php

namespace inisire\NetBus\Query;

interface QueryInterface
{
    public function getId(): string;

    public function getName(): string;

    public function getData(): array;
}