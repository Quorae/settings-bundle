<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Contract;

interface SettingsDefinitionProviderInterface
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function getAllDefinitions(): array;

    /**
     * @return array<string, mixed>
     */
    public function getDefinition(string $group): array;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getDefinitionsByScope(string $scope): array;

    /**
     * @return array<string, mixed>
     */
    public function getDefaults(string $group): array;

    /**
     * @return list<string>
     */
    public function getOverriddenKeys(string $group, string $scope = 'global'): array;

    public function hasGroup(string $group): bool;
}
