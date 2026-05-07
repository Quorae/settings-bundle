<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Dto;

final readonly class SettingFieldDefinition
{
    /** @var list<string> */
    public const array ALLOWED_TYPES = [
        'text',
        'textarea',
        'choice',
        'boolean',
        'integer',
        'float',
        'password',
        'markdown',
    ];

    /**
     * @param list<array<string, mixed>> $rules
     * @param array<string, string>      $choices
     */
    public function __construct(
        public string $key,
        public string $type,
        public string $label,
        public ?string $description,
        public ?string $envKey,
        public ?string $default,
        public bool $encrypted,
        public array $rules,
        public array $choices,
    ) {
    }

    /**
     * @param array<string, mixed> $raw
     */
    public static function fromArray(string $key, array $raw): self
    {
        $type = self::requireString($raw, 'type');
        if (!\in_array($type, self::ALLOWED_TYPES, true)) {
            throw new \InvalidArgumentException(\sprintf('Unknown settings field type "%s" for key "%s". Allowed: %s.', $type, $key, implode(', ', self::ALLOWED_TYPES)));
        }

        $label = isset($raw['label']) && \is_string($raw['label']) ? $raw['label'] : $key;
        $description = isset($raw['description']) && \is_string($raw['description']) ? $raw['description'] : null;
        $envKey = isset($raw['env_key']) && \is_string($raw['env_key']) ? $raw['env_key'] : null;

        $default = null;
        if (\array_key_exists('default', $raw) && null !== $raw['default']) {
            $default = \is_scalar($raw['default']) ? (string) $raw['default'] : null;
        }

        $encrypted = isset($raw['encrypted']) && true === $raw['encrypted'];

        $rules = [];
        if (isset($raw['rules']) && \is_array($raw['rules'])) {
            foreach ($raw['rules'] as $rule) {
                if (\is_array($rule)) {
                    $rules[] = $rule;
                }
            }
        }

        $choices = [];
        if (isset($raw['choices']) && \is_array($raw['choices'])) {
            foreach ($raw['choices'] as $value => $choiceLabel) {
                $choices[(string) $value] = \is_string($choiceLabel) ? $choiceLabel : (string) $value;
            }
        }

        return new self(
            key: $key,
            type: $type,
            label: $label,
            description: $description,
            envKey: $envKey,
            default: $default,
            encrypted: $encrypted,
            rules: $rules,
            choices: $choices,
        );
    }

    /**
     * @param array<string, mixed> $raw
     */
    private static function requireString(array $raw, string $key): string
    {
        if (!isset($raw[$key]) || !\is_string($raw[$key])) {
            throw new \InvalidArgumentException(\sprintf('Missing or invalid "%s" in field definition.', $key));
        }

        return $raw[$key];
    }
}
