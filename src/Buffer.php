<?php

namespace inisire\NetBus;

class Buffer
{
    public function __construct(
        private string $data = ''
    )
    {
    }

    public function write(string $data): void
    {
        $this->data .= $data;
    }

    public function consume(): iterable
    {
        if (!str_contains($this->data, PHP_EOL)) {
            return;
        }

        $offset = 0;

        while (($pos = strpos($this->data, PHP_EOL, $offset)) !== false) {
            $chunk = substr($this->data, $offset, $pos - $offset);
            $offset = $pos + 1;
            yield $chunk;
        }

        if ($offset === strlen($this->data)) {
            $this->data = '';
        } else {
            $this->data = substr($this->data, $offset);
        }
    }
}