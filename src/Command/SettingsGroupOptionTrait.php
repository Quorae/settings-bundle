<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

trait SettingsGroupOptionTrait
{
    private function configureGroupOption(string $description): void
    {
        $this->addOption(
            'group',
            null,
            InputOption::VALUE_REQUIRED,
            $description,
        );
    }

    private function resolveGroupOption(InputInterface $input): ?string
    {
        $value = $input->getOption('group');
        if (null === $value) {
            return null;
        }
        \assert(\is_string($value));

        return $value;
    }
}
