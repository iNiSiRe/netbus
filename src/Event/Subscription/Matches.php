<?php

namespace inisire\NetBus\Event\Subscription;

class Matches implements MatcherInterface
{
    public function __construct(
        private readonly array $entries = []
    )
    {
    }

    public function getEntries(): array
    {
        return $this->entries;
    }

    public function match(string $name): bool
    {
        return in_array($name, $this->entries);
    }
}