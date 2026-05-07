<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Command;

use Quorae\SettingsBundle\Contract\SettingsWriterInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'quorae:settings:clear',
    description: 'Purge the settings cache (tag "settings" + in-memory memoization).',
)]
final class SettingsClearCommand extends Command
{
    public function __construct(
        private readonly SettingsWriterInterface $settingsWriter,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->settingsWriter->clearCache();
        $io->success('Settings cache purged.');

        return Command::SUCCESS;
    }
}
