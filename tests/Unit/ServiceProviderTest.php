<?php

namespace Javaabu\Meta\Tests\Unit;

use Javaabu\Meta\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ServiceProviderTest extends TestCase
{
    #[Test]
    public function it_merges_the_default_config(): void
    {
        $this->assertSame('meta', config('meta.table'));
    }
}
