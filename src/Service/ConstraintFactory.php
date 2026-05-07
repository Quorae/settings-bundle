<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Service;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Url;

final readonly class ConstraintFactory
{
    /**
     * @param list<array<string, mixed>> $raw
     *
     * @return list<Constraint>
     */
    public function build(array $raw): array
    {
        $constraints = [];
        foreach ($raw as $entry) {
            if (!\is_array($entry) || 1 !== \count($entry)) {
                throw new \InvalidArgumentException('Settings constraint spec: each entry must be a single-key associative array like {"NotBlank": null}.');
            }

            /** @var string $name */
            $name = array_key_first($entry);
            if (!\is_string($name)) {
                throw new \InvalidArgumentException('Settings constraint spec: constraint name must be a string.');
            }

            $options = $entry[$name];
            $constraints[] = $this->instantiate($name, $options);
        }

        return $constraints;
    }

    private function instantiate(string $name, mixed $options): Constraint
    {
        return match ($name) {
            'NotBlank' => new NotBlank(),
            'NotNull' => new NotNull(),
            'Length' => $this->buildLength($this->requireOptionsArray($name, $options)),
            'Choice' => $this->buildChoice($this->requireOptionsArray($name, $options)),
            'Range' => $this->buildRange($this->requireOptionsArray($name, $options)),
            'Regex' => $this->buildRegex($this->requireOptionsArray($name, $options)),
            'Type' => new Type(type: $this->requireTypeArg($options)),
            'Email' => new Email(mode: Email::VALIDATION_MODE_HTML5),
            'Url' => new Url(requireTld: true),
            default => throw new \InvalidArgumentException(\sprintf('Settings constraint spec: unknown constraint "%s". Supported: NotBlank, NotNull, Length, Choice, Range, Regex, Type, Url, Email.', $name)),
        };
    }

    /**
     * @param array<string, mixed> $options
     */
    private function buildLength(array $options): Length
    {
        $min = isset($options['min']) && \is_int($options['min']) && $options['min'] >= 0 ? $options['min'] : null;
        $max = isset($options['max']) && \is_int($options['max']) && $options['max'] >= 1 ? $options['max'] : null;

        return new Length(min: $min, max: $max);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function buildChoice(array $options): Choice
    {
        if (!isset($options['choices']) || !\is_array($options['choices'])) {
            throw new \InvalidArgumentException('Settings constraint spec: constraint "Choice" requires a "choices" option (array).');
        }

        return new Choice(choices: array_values($options['choices']));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function buildRange(array $options): Range
    {
        $min = \array_key_exists('min', $options) ? $options['min'] : null;
        $max = \array_key_exists('max', $options) ? $options['max'] : null;

        return new Range(min: $min, max: $max);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function buildRegex(array $options): Regex
    {
        if (!isset($options['pattern']) || !\is_string($options['pattern'])) {
            throw new \InvalidArgumentException('Settings constraint spec: constraint "Regex" requires a string "pattern" option.');
        }

        return new Regex(pattern: $options['pattern']);
    }

    /**
     * @return string|list<string>
     */
    private function requireTypeArg(mixed $options): string|array
    {
        if (\is_string($options)) {
            return $options;
        }
        if (\is_array($options) && isset($options['type'])) {
            $type = $options['type'];
            if (\is_string($type)) {
                return $type;
            }
            if (\is_array($type)) {
                return array_values(array_filter($type, 'is_string'));
            }
        }

        throw new \InvalidArgumentException('Settings constraint spec: constraint "Type" must receive a scalar type name or an options array with a "type" key.');
    }

    /**
     * @return array<string, mixed>
     */
    private function requireOptionsArray(string $name, mixed $options): array
    {
        if (!\is_array($options)) {
            throw new \InvalidArgumentException(\sprintf('Settings constraint spec: constraint "%s" requires an options array.', $name));
        }

        return $options;
    }
}
