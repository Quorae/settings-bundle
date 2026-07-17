<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Command;

use Quorae\SettingsBundle\Contract\SettingsDefinitionProviderInterface;
use Quorae\SettingsBundle\Contract\SettingsWriterInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Write a runtime override for a single setting field.
 *
 * Usage: bin/console quorae:settings:set <group> <key> <value>
 *
 * The value is cast to the field's declared type before persistence.
 * Encrypted fields are accepted as plaintext — the writer handles encryption.
 */
#[AsCommand(
    name: 'quorae:settings:set',
    description: 'Set a runtime override for a single settings field.',
)]
final class SettingsSetCommand extends Command
{
    public function __construct(
        private readonly SettingsWriterInterface $settingsWriter,
        private readonly SettingsDefinitionProviderInterface $definitionProvider,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('group', InputArgument::REQUIRED, 'Settings group name (e.g. legal)')
            ->addArgument('key', InputArgument::REQUIRED, 'Field key within the group (e.g. terms_version)')
            ->addArgument('value', InputArgument::REQUIRED, 'New value to store as a runtime override');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $group = $input->getArgument('group');
        $key = $input->getArgument('key');
        $value = $input->getArgument('value');

        \assert(\is_string($group));
        \assert(\is_string($key));
        \assert(\is_string($value));

        if (!$this->definitionProvider->hasGroup($group)) {
            $io->error(\sprintf('Group "%s" does not exist.', $group));

            return Command::FAILURE;
        }

        $definitions = $this->definitionProvider->getAllDefinitions();
        $fields = $definitions[$group]['fields'] ?? [];

        if (!\array_key_exists($key, $fields)) {
            $io->error(\sprintf('Field "%s" does not exist in group "%s".', $key, $group));

            return Command::FAILURE;
        }

        $this->settingsWriter->save($group, [$key => $value]);
        $io->success(\sprintf('[%s] %s = %s', $group, $key, $value));

        return Command::SUCCESS;
    }
}
