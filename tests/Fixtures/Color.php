<?php

namespace Javaabu\Meta\Tests\Fixtures;

use Stringable;

final readonly class Color implements Stringable
{
    public function __construct(public string $hex) {}

    public function __toString(): string
    {
        return $this->hex;
    }
}
