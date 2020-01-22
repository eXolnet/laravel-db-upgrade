<?php

namespace Exolnet\DbUpgrade\Tests\Unit;

use Exolnet\DbUpgrade\Console\DbUpgradeCommand;
use Illuminate\Filesystem\Filesystem;
use Mockery as m;
use Symfony\Component\Process\ExecutableFinder;

class DbUpgradeCommandTest extends UnitTest
{
    /**
     * @var \Illuminate\Filesystem\Filesystem|\Mockery\LegacyMockInterface|\Mockery\MockInterface
     */
    protected $filesystem;

    /**
     * @var \Mockery\LegacyMockInterface|\Mockery\MockInterface|\Symfony\Component\Process\ExecutableFinder
     */
    protected $executableFinder;

    /**
     * @var \Exolnet\DbUpgrade\Console\DbUpgradeCommand
     */
    protected $command;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = m::mock(Filesystem::class);
        $this->executableFinder = m::mock(ExecutableFinder::class);

        $this->command = new DbUpgradeCommand($this->filesystem, $this->executableFinder);
    }

    /**
     * @return void
     */
    public function testItCanBeInitialized(): void
    {
        $this->assertInstanceOf(DbUpgradeCommand::class, $this->command);
    }
}
