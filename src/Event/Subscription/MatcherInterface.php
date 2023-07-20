<?php

namespace inisire\NetBus\Event\Subscription;

interface MatcherInterface
{
    /**
     * @return array<string>
     */
    public function getEntries(): array;

    public function match(string $name): bool;
}