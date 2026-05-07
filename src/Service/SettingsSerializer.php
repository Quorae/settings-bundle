<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Service;

final readonly class SettingsSerializer
{
    public const string MASKED_SENTINEL = '********';

    public function serialize(mixed $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (\is_array($value)) {
            return json_encode($value, \JSON_THROW_ON_ERROR);
        }

        return (string) $value;
    }

    public function isMaskedSentinel(mixed $value): bool
    {
        return self::MASKED_SENTINEL === $value;
    }
}
