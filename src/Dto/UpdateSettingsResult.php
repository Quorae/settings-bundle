<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Dto;

final readonly class UpdateSettingsResult
{
    /**
     * @param array<string, list<string>> $errorsByField
     */
    public function __construct(
        public bool $valid,
        public array $errorsByField,
        public ?SettingsGroup $freshGroup,
    ) {
    }

    /**
     * @param array<string, list<string>> $errorsByField
     */
    public static function invalid(array $errorsByField): self
    {
        return new self(valid: false, errorsByField: $errorsByField, freshGroup: null);
    }

    public static function valid(SettingsGroup $freshGroup): self
    {
        return new self(valid: true, errorsByField: [], freshGroup: $freshGroup);
    }
}
