<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Contract;

interface SettingsWriterInterface
{
    /**
     * @param array<string, mixed> $values
     */
    public function save(string $group, array $values, string $scope = 'global'): void;

    public function resetToDefault(string $group, string $key, string $scope = 'global'): void;

    public function clearCache(): void;
}
