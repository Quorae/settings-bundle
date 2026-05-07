<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Command;

use Quorae\SettingsBundle\Contract\SettingsDefinitionProviderInterface;
use Quorae\SettingsBundle\Contract\SettingsReaderInterface;
use Quorae\SettingsBundle\Service\SettingsFieldParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'quorae:settings:cache',
    description: 'Warm the settings cache by resolving every YAML group.',
)]
final class SettingsCacheCommand extends Command
{
    use SettingsGroupOptionTrait;

    public function __construct(
        private readonly SettingsReaderInterface $settingsReader,
        private readonly SettingsDefinitionProviderInterface $definitionProvider,
        private readonly SettingsFieldParser $fieldParser,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->configureGroupOption('Warm only the specified group');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $onlyGroup = $this->resolveGroupOption($input);

        $definitions = $this->definitionProvider->getAllDefinitions();
        if (null !== $onlyGroup) {
            if (!$this->definitionProvider->hasGroup($onlyGroup)) {
                $io->error(\sprintf('Group "%s" does not exist.', $onlyGroup));

                return Command::FAILURE;
            }
            $definitions = [$onlyGroup => $definitions[$onlyGroup]];
        }

        $rows = [];
        foreach ($definitions as $name => $definition) {
            $start = hrtime(true);
            $this->settingsReader->getGroup($name);
            $elapsedMs = (hrtime(true) - $start) / 1_000_000.0;

            $rows[] = [
                $name,
                $this->fieldParser->groupLabel($name, $definition),
                'yes',
                \sprintf('%.2f', $elapsedMs),
            ];
        }

        $io->table(['group', 'label', 'cached', 'elapsed_ms'], $rows);
        $io->success(\sprintf('%d group(s) warmed.', \count($rows)));

        return Command::SUCCESS;
    }
}
