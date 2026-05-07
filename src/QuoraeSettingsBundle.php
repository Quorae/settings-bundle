<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class QuoraeSettingsBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
