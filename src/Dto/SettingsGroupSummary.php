<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Dto;

final readonly class SettingsGroupSummary
{
    public function __construct(
        public string $name,
        public string $label,
        public int $fieldsCount,
        public int $overriddenCount,
        public int $order = 0,
    ) {
    }
}
