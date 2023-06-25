<?php

namespace inisire\NetBus\Logging;

use Psr\Log\AbstractLogger;

class EchoLogger extends AbstractLogger
{
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        echo sprintf('[%s] %s %s', $level, $message, json_encode($context)) . PHP_EOL;
    }
}