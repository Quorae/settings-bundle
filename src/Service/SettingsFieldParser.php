<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Service;

use Quorae\SettingsBundle\Dto\SettingFieldDefinition;

final readonly class SettingsFieldParser
{
    /**
     * @param array<string, mixed> $definition
     *
     * @return array<string, SettingFieldDefinition>
     */
    public function parse(array $definition): array
    {
        $rawFields = $definition['fields'] ?? [];
        if (!\is_array($rawFields)) {
            return [];
        }

        $fields = [];
        foreach ($rawFields as $key => $raw) {
            if (!\is_string($key) || !\is_array($raw)) {
                continue;
            }
            $fields[$key] = SettingFieldDefinition::fromArray($key, $raw);
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $definition
     */
    public function groupLabel(string $groupName, array $definition): string
    {
        $label = $definition['group_label'] ?? null;

        return \is_string($label) ? $label : ucfirst(str_replace('_', ' ', $groupName));
    }
}
