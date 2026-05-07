<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Service;

use Quorae\SettingsBundle\Dto\SettingFieldDefinition;

final readonly class SettingsDisplayHelper
{
    public function shouldMask(SettingFieldDefinition $field): bool
    {
        return $field->encrypted || 'password' === $field->type;
    }

    public function maskIfSensitive(SettingFieldDefinition $field, mixed $value): mixed
    {
        if ($this->shouldMask($field)) {
            return SettingsSerializer::MASKED_SENTINEL;
        }

        return $value;
    }

    public function renderForDisplay(SettingFieldDefinition $field, mixed $value): string
    {
        if ($this->shouldMask($field)) {
            return SettingsSerializer::MASKED_SENTINEL;
        }

        if (null === $value) {
            return '';
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (\is_array($value)) {
            return json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }
}
