<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Dto;

/**
 * @implements \ArrayAccess<string, mixed>
 * @implements \IteratorAggregate<string, mixed>
 */
final class SettingsGroup implements \ArrayAccess, \Countable, \IteratorAggregate
{
    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $values
     * @param list<string>         $overriddenKeys
     */
    public function __construct(
        private readonly string $group,
        private readonly string $groupLabel,
        private readonly string $scope,
        private readonly array $definition,
        private readonly array $values,
        private readonly array $overriddenKeys,
    ) {
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function getGroupLabel(): string
    {
        return $this->groupLabel;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefinition(): array
    {
        return $this->definition;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFields(): array
    {
        $fields = $this->definition['fields'] ?? [];

        return \is_array($fields) ? $fields : [];
    }

    /**
     * @return list<string>
     */
    public function getOverriddenKeys(): array
    {
        return $this->overriddenKeys;
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->values);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }

    public function __get(string $name): mixed
    {
        return $this->values[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return \array_key_exists($name, $this->values);
    }

    public function offsetExists(mixed $offset): bool
    {
        return \array_key_exists($offset, $this->values);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->values[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('SettingsGroup is immutable. Use SettingsWriterInterface::save() to persist changes.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('SettingsGroup is immutable. Use SettingsWriterInterface::save() to persist changes.');
    }

    public function count(): int
    {
        return \count($this->values);
    }

    /**
     * @return \ArrayIterator<string, mixed>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->values);
    }
}
