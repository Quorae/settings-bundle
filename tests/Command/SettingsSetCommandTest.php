<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Quorae\SettingsBundle\Command\SettingsSetCommand;
use Quorae\SettingsBundle\Contract\SettingsDefinitionProviderInterface;
use Quorae\SettingsBundle\Contract\SettingsWriterInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SettingsSetCommandTest extends TestCase
{
    public function testSetPersistsOverrideForExistingField(): void
    {
        $writer = $this->createMock(SettingsWriterInterface::class);
        $writer->expects(self::once())
            ->method('save')
            ->with('payment', ['enabled' => 'true']);

        $definitions = $this->createMock(SettingsDefinitionProviderInterface::class);
        $definitions->method('hasGroup')->with('payment')->willReturn(true);
        $definitions->method('getAllDefinitions')->willReturn([
            'payment' => ['fields' => ['enabled' => ['type' => 'bool']]],
        ]);

        $tester = new CommandTester(new SettingsSetCommand($writer, $definitions));
        $exit = $tester->execute(['group' => 'payment', 'key' => 'enabled', 'value' => 'true']);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('[payment] enabled = true', $tester->getDisplay());
    }

    public function testSetFailsForUnknownGroup(): void
    {
        $writer = $this->createMock(SettingsWriterInterface::class);
        $writer->expects(self::never())->method('save');

        $definitions = $this->createMock(SettingsDefinitionProviderInterface::class);
        $definitions->method('hasGroup')->with('ghost')->willReturn(false);

        $tester = new CommandTester(new SettingsSetCommand($writer, $definitions));
        $exit = $tester->execute(['group' => 'ghost', 'key' => 'enabled', 'value' => 'true']);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('does not exist', $tester->getDisplay());
    }

    public function testSetFailsForUnknownFieldInGroup(): void
    {
        $writer = $this->createMock(SettingsWriterInterface::class);
        $writer->expects(self::never())->method('save');

        $definitions = $this->createMock(SettingsDefinitionProviderInterface::class);
        $definitions->method('hasGroup')->with('payment')->willReturn(true);
        $definitions->method('getAllDefinitions')->willReturn([
            'payment' => ['fields' => ['enabled' => ['type' => 'bool']]],
        ]);

        $tester = new CommandTester(new SettingsSetCommand($writer, $definitions));
        $exit = $tester->execute(['group' => 'payment', 'key' => 'ghost', 'value' => 'x']);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('does not exist in group', $tester->getDisplay());
    }
}
