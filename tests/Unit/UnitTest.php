<?php

namespace Exolnet\DbUpgrade\Tests\Unit;

use Mockery;
use PHPUnit\Framework\TestCase;

abstract class UnitTest extends TestCase
{
    public function tearDown(): void
    {
        Mockery::close();
    }
}
