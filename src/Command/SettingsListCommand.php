<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Command;

use Quorae\SettingsBundle\Contract\SettingsDefinitionProviderInterface;
use Quorae\SettingsBundle\Contract\SettingsReaderInterface;
use Quorae\SettingsBundle\Dto\SettingsGroup;
use Quorae\SettingsBundle\Service\SettingsDisplayHelper;
use Quorae\SettingsBundle\Service\SettingsFieldParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'quorae:settings:list',
    description: 'Display resolved settings groups, field by field, with encrypted values masked.',
)]
final class SettingsListCommand extends Command
{
    use SettingsGroupOptionTrait;

    public function __construct(
        private readonly SettingsReaderInterface $settingsReader,
        private readonly SettingsDefinitionProviderInterface $definitionProvider,
        private readonly SettingsFieldParser $fieldParser,
        private readonly SettingsDisplayHelper $displayHelper,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->configureGroupOption('Limit display to the specified group');
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

        foreach ($definitions as $name => $definition) {
            $resolved = $this->settingsReader->getGroup($name);
            $io->section(\sprintf('%s — %s', $name, $resolved->getGroupLabel()));
            $io->table(
                ['key', 'type', 'value', 'overridden'],
                $this->rowsFor($resolved, $definition),
            );
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return list<array{string, string, string, string}>
     */
    private function rowsFor(SettingsGroup $resolved, array $definition): array
    {
        $rows = [];
        $overriddenKeys = $resolved->getOverriddenKeys();
        $fields = $this->fieldParser->parse($definition);

        foreach ($fields as $key => $field) {
            $value = $resolved->all()[$key] ?? null;
            $rows[] = [
                $key,
                $field->type,
                $this->displayHelper->renderForDisplay($field, $value),
                \in_array($key, $overriddenKeys, true) ? 'yes' : 'no',
            ];
        }

        return $rows;
    }
}
