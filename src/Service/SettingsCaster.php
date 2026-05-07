<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Service;

use Quorae\SettingsBundle\Dto\SettingFieldDefinition;

final readonly class SettingsCaster
{
    public function cast(SettingFieldDefinition $field, ?string $raw): mixed
    {
        if (null === $raw) {
            return null;
        }

        return match ($field->type) {
            'boolean' => '1' === $raw || 'true' === $raw,
            'integer' => (int) $raw,
            'float' => (float) $raw,
            default => $raw,
        };
    }
}
