<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Command;

use Quorae\SettingsBundle\Service\SettingsEncryptionVerifier;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'quorae:settings:check-encryption',
    description: 'Verify that all encrypted overrides can be decrypted with the current secret.',
)]
final class SettingsCheckEncryptionCommand extends Command
{
    public function __construct(
        private readonly SettingsEncryptionVerifier $verifier,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $failures = $this->verifier->verifyAll();

        if ([] !== $failures) {
            $rows = array_map(
                static fn (array $f): array => [$f['group'], $f['key'], $f['error']],
                $failures,
            );
            $io->table(['group', 'key', 'error'], $rows);
            $io->error(\sprintf('%d encrypted field(s) unreadable — probable APP_SECRET rotation.', \count($failures)));

            return Command::FAILURE;
        }

        $verifiedCount = $this->verifier->countVerified();
        $io->success(\sprintf('%d encrypted field(s) verified.', $verifiedCount));

        return Command::SUCCESS;
    }
}
