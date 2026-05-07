<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle;

use Quorae\SettingsBundle\DependencyInjection\QuoraeSettingsExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class QuoraeSettingsBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        return new QuoraeSettingsExtension();
    }
}
