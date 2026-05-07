<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Contract;

use Quorae\SettingsBundle\Dto\SettingsGroup;

interface SettingsReaderInterface
{
    public function getGroup(string $group, string $scope = 'global'): SettingsGroup;

    public function getValue(string $group, string $key, mixed $default = null, string $scope = 'global'): mixed;

    public function hasGroup(string $group): bool;
}
