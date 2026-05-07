<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Contract;

interface SettingOverrideRepositoryInterface
{
    /**
     * @return array<string, ?string>
     */
    public function getGroupOverrides(string $group, string $scope): array;

    /**
     * @param array<string, ?string> $values
     */
    public function setMany(string $group, array $values, string $scope): void;

    public function delete(string $group, string $key, string $scope): void;

    /**
     * @param list<string> $keys
     */
    public function deleteMany(string $group, array $keys, string $scope): void;

    public function deleteGroup(string $group, string $scope): void;
}
