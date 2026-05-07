<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Service;

use Quorae\SettingsBundle\Contract\SettingOverrideRepositoryInterface;
use Quorae\SettingsBundle\Contract\SettingsDefinitionProviderInterface;
use Quorae\SettingsBundle\Exception\DecryptException;

final readonly class SettingsEncryptionVerifier
{
    public function __construct(
        private SettingsDefinitionProviderInterface $definitionProvider,
        private SettingOverrideRepositoryInterface $overrides,
        private SettingCryptor $cryptor,
        private SettingsFieldParser $fieldParser,
        private string $allowedScope = 'global',
    ) {
    }

    /**
     * @return list<array{group: string, key: string, error: string}>
     */
    public function verifyAll(): array
    {
        $failures = [];
        foreach ($this->definitionProvider->getAllDefinitions() as $groupName => $definition) {
            $overrides = $this->overrides->getGroupOverrides($groupName, $this->allowedScope);
            if ([] === $overrides) {
                continue;
            }

            $fields = $this->fieldParser->parse($definition);

            foreach ($fields as $key => $field) {
                if (!$field->encrypted || !\array_key_exists($key, $overrides) || null === $overrides[$key]) {
                    continue;
                }
                try {
                    $this->cryptor->decrypt($overrides[$key]);
                } catch (DecryptException $e) {
                    $failures[] = ['group' => $groupName, 'key' => $key, 'error' => $e->getMessage()];
                }
            }
        }

        return $failures;
    }

    public function countVerified(): int
    {
        $count = 0;
        foreach ($this->definitionProvider->getAllDefinitions() as $groupName => $definition) {
            $overrides = $this->overrides->getGroupOverrides($groupName, $this->allowedScope);
            if ([] === $overrides) {
                continue;
            }

            $fields = $this->fieldParser->parse($definition);

            foreach ($fields as $key => $field) {
                if (!$field->encrypted || !\array_key_exists($key, $overrides) || null === $overrides[$key]) {
                    continue;
                }
                try {
                    $this->cryptor->decrypt($overrides[$key]);
                    ++$count;
                } catch (DecryptException) {
                }
            }
        }

        return $count;
    }
}
