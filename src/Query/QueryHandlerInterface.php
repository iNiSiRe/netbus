<?php

namespace inisire\NetBus\Query;

interface QueryHandlerInterface
{
    public function getSubscribedQueries(): array;
}