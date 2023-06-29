<?php

namespace inisire\NetBus\Logging;

use inisire\NetBus\Event\Event;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

class EventDispatchingLogger implements LoggerInterface
{
    use LoggerTrait;

    public function __construct(
        private readonly EventDispatcherInterface $dispatcher
    )
    {
    }

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->dispatcher->dispatch(new Event('log', ['level' => $level, 'message' => $message, 'context' => $context]));
    }
}