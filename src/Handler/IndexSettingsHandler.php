<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Handler;

use Quorae\SettingsBundle\Contract\SettingsDefinitionProviderInterface;
use Quorae\SettingsBundle\Dto\SettingsGroupSummary;
use Quorae\SettingsBundle\Service\SettingsFieldParser;

final readonly class IndexSettingsHandler
{
    public function __construct(
        private SettingsDefinitionProviderInterface $definitionProvider,
        private SettingsFieldParser $fieldParser,
        private string $defaultScope = 'global',
    ) {
    }

    /**
     * @return list<SettingsGroupSummary>
     */
    public function handle(): array
    {
        $summaries = [];
        foreach ($this->definitionProvider->getDefinitionsByScope($this->defaultScope) as $groupName => $definition) {
            $summaries[] = new SettingsGroupSummary(
                name: $groupName,
                label: $this->fieldParser->groupLabel($groupName, $definition),
                fieldsCount: $this->countFields($definition),
                overriddenCount: \count($this->definitionProvider->getOverriddenKeys($groupName)),
                order: \is_int($definition['order'] ?? null) ? $definition['order'] : 0,
            );
        }

        usort(
            $summaries,
            static fn (SettingsGroupSummary $a, SettingsGroupSummary $b): int => $a->order <=> $b->order ?: strcasecmp($a->label, $b->label),
        );

        return $summaries;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function countFields(array $definition): int
    {
        $fields = $definition['fields'] ?? [];

        return \is_array($fields) ? \count($fields) : 0;
    }
}
