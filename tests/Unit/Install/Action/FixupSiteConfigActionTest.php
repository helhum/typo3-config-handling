<?php
declare(strict_types=1);
namespace Helhum\TYPO3\ConfigHandling\Tests\Unit\Install\Action;

use Helhum\TYPO3\ConfigHandling\Install\Action\FixupSiteConfigAction;
use Helhum\Typo3Console\Mvc\Cli\CommandDispatcher;
use Helhum\Typo3Console\Mvc\Cli\ConsoleOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FixupSiteConfigActionTest extends TestCase
{
    #[Test]
    public function acceptsOutputWithoutCreatingDynamicProperties(): void
    {
        $action = new FixupSiteConfigAction();
        $action->setOutput($this->createStub(ConsoleOutput::class));

        self::assertSame([], get_object_vars($action));
    }

    #[Test]
    public function acceptsCommandDispatcherWithoutCreatingDynamicProperties(): void
    {
        $action = new FixupSiteConfigAction();
        $action->setCommandDispatcher($this->createStub(CommandDispatcher::class));

        self::assertSame([], get_object_vars($action));
    }
}
