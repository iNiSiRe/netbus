<?php

namespace inisire\NetBus\Event\Subscription;

class Wildcard extends Matches
{
    public function __construct(
        private readonly array $excludes = []
    )
    {
        parent::__construct();
    }

    public function getEntries(): array
    {
        return ['*'];
    }

    public function match(string $name): bool
    {
        return !in_array($this, $this->excludes);
    }
}