<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Service;

use Quorae\SettingsBundle\Contract\SettingOverrideRepositoryInterface;
use Quorae\SettingsBundle\Contract\SettingsDefinitionProviderInterface;
use Quorae\SettingsBundle\Contract\SettingsReaderInterface;
use Quorae\SettingsBundle\Contract\SettingsWriterInterface;
use Quorae\SettingsBundle\Dto\SettingsGroup;
use Quorae\SettingsBundle\Exception\DecryptException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class SettingsManager implements SettingsReaderInterface, SettingsWriterInterface, SettingsDefinitionProviderInterface
{
    private const string CACHE_TAG = 'settings';
    private const int CACHE_TTL = 3600;

    /** @var array<string, SettingsGroup> */
    private array $resolved = [];

    public function __construct(
        private readonly SettingsDefinitionLoader $loader,
        private readonly SettingOverrideRepositoryInterface $overrides,
        private readonly SettingsCaster $caster,
        private readonly SettingsSerializer $serializer,
        private readonly SettingCryptor $cryptor,
        private readonly SettingsFieldParser $fieldParser,
        private readonly TagAwareCacheInterface $cache,
        private readonly string $allowedScope = 'global',
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function getGroup(string $group, string $scope = 'global'): SettingsGroup
    {
        $scope = $scope ?: $this->allowedScope;
        $this->assertAllowedScope($scope);

        if (isset($this->resolved[$group])) {
            return $this->resolved[$group];
        }

        $definition = $this->requireDefinition($group);

        /** @var array{values: array<string, mixed>, overriddenKeys: list<string>} $cached */
        $cached = $this->cache->get($this->cacheKey($group), function (ItemInterface $item) use ($group, $definition): array {
            $item->expiresAfter(self::CACHE_TTL);
            $item->tag(self::CACHE_TAG);

            return $this->resolveValues($group, $definition);
        });

        $resolvedGroup = new SettingsGroup(
            group: $group,
            groupLabel: $this->groupLabel($group, $definition),
            scope: $this->allowedScope,
            definition: $definition,
            values: $cached['values'],
            overriddenKeys: $cached['overriddenKeys'],
        );

        $this->resolved[$group] = $resolvedGroup;

        return $resolvedGroup;
    }

    public function getValue(string $group, string $key, mixed $default = null, string $scope = 'global'): mixed
    {
        $scope = $scope ?: $this->allowedScope;
        $this->assertAllowedScope($scope);
        $group = $this->getGroup($group);

        return $group->has($key) ? $group->all()[$key] : $default;
    }

    public function hasGroup(string $group): bool
    {
        return \array_key_exists($group, $this->loader->loadAll());
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaults(string $group): array
    {
        $definition = $this->requireDefinition($group);
        $fields = $this->fieldParser->parse($definition);

        $defaults = [];
        foreach ($fields as $key => $field) {
            $raw = $this->loader->resolveDefault($definition['fields'][$key]);
            $defaults[$key] = $this->caster->cast($field, $raw);
        }

        return $defaults;
    }

    /**
     * @return list<string>
     */
    public function getOverriddenKeys(string $group, string $scope = 'global'): array
    {
        $scope = $scope ?: $this->allowedScope;
        $this->assertAllowedScope($scope);

        if (isset($this->resolved[$group])) {
            return $this->resolved[$group]->getOverriddenKeys();
        }

        return array_keys($this->overrides->getGroupOverrides($group, $this->allowedScope));
    }

    public function resetToDefault(string $group, string $key, string $scope = 'global'): void
    {
        $scope = $scope ?: $this->allowedScope;
        $this->assertAllowedScope($scope);
        $this->overrides->delete($group, $key, $this->allowedScope);
        $this->invalidate($group);
    }

    public function save(string $group, array $values, string $scope = 'global'): void
    {
        $scope = $scope ?: $this->allowedScope;
        $this->assertAllowedScope($scope);
        $definition = $this->requireDefinition($group);
        $fields = $this->fieldParser->parse($definition);
        $defaults = $this->getDefaults($group);

        $toSave = [];
        $touchedKeys = [];
        foreach ($values as $key => $value) {
            if (!isset($fields[$key])) {
                continue;
            }

            $touchedKeys[] = $key;
            $field = $fields[$key];

            if ($this->serializer->isMaskedSentinel($value)) {
                continue;
            }

            $serialized = $this->serializer->serialize($value);
            $serializedDefault = $this->serializer->serialize($defaults[$key] ?? null);
            if ($serialized === $serializedDefault) {
                continue;
            }

            if (null === $serialized) {
                continue;
            }

            $toSave[$key] = $field->encrypted
                ? $this->cryptor->encrypt($serialized)
                : $serialized;
        }

        $this->overrides->setMany($group, $toSave, $this->allowedScope);

        $keysToDelete = array_values(array_diff($touchedKeys, array_keys($toSave)));
        $this->overrides->deleteMany($group, $keysToDelete, $this->allowedScope);

        $this->invalidate($group);

        $this->logger->debug('Settings saved', [
            'group' => $group,
            'saved_keys' => array_keys($toSave),
            'deleted_keys' => $keysToDelete,
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getAllDefinitions(): array
    {
        return $this->loader->loadAll();
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefinition(string $group): array
    {
        return $this->requireDefinition($group);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getDefinitionsByScope(string $scope): array
    {
        $all = $this->loader->loadAll();

        return array_filter(
            $all,
            fn (array $definition): bool => ($definition['scope'] ?? $this->allowedScope) === $scope,
        );
    }

    public function clearCache(): void
    {
        $this->resolved = [];
        $this->cache->invalidateTags([self::CACHE_TAG]);
    }

    public function getAllowedScope(): string
    {
        return $this->allowedScope;
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return array{values: array<string, mixed>, overriddenKeys: list<string>}
     */
    private function resolveValues(string $group, array $definition): array
    {
        $fields = $this->fieldParser->parse($definition);
        $overrides = $this->overrides->getGroupOverrides($group, $this->allowedScope);

        $values = [];
        $effectiveOverrides = [];
        foreach ($fields as $key => $field) {
            if (\array_key_exists($key, $overrides)) {
                $raw = $overrides[$key];
                if ($field->encrypted && null !== $raw) {
                    try {
                        $raw = $this->cryptor->decrypt($raw);
                    } catch (DecryptException $e) {
                        $this->logger->warning('Settings: failed to decrypt override, falling back to default.', [
                            'group' => $group,
                            'key' => $key,
                            'exception' => $e->getMessage(),
                        ]);
                        $raw = $this->loader->resolveDefault($definition['fields'][$key]);
                        $values[$key] = $this->caster->cast($field, $raw);

                        continue;
                    }
                }

                $values[$key] = $this->caster->cast($field, $raw);
                $effectiveOverrides[] = $key;

                continue;
            }

            $raw = $this->loader->resolveDefault($definition['fields'][$key]);
            $values[$key] = $this->caster->cast($field, $raw);
        }

        return ['values' => $values, 'overriddenKeys' => $effectiveOverrides];
    }

    /**
     * @return array<string, mixed>
     */
    private function requireDefinition(string $group): array
    {
        $all = $this->loader->loadAll();
        if (!isset($all[$group])) {
            throw new \RuntimeException(\sprintf('Settings group "%s" is not defined.', $group));
        }

        return $all[$group];
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function groupLabel(string $group, array $definition): string
    {
        return $this->fieldParser->groupLabel($group, $definition);
    }

    private function invalidate(string $group): void
    {
        unset($this->resolved[$group]);
        $this->cache->delete($this->cacheKey($group));
    }

    private function cacheKey(string $group): string
    {
        return 'settings.'.$group.'.'.$this->allowedScope;
    }

    private function assertAllowedScope(string $scope): void
    {
        if ($this->allowedScope !== $scope) {
            throw new \LogicException(\sprintf(
                'Settings manager: scope "%s" is not supported. Only "%s" is allowed.',
                $scope,
                $this->allowedScope,
            ));
        }
    }
}
