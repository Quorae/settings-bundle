<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Handler;

use Quorae\SettingsBundle\Contract\SettingsDefinitionProviderInterface;
use Quorae\SettingsBundle\Contract\SettingsReaderInterface;
use Quorae\SettingsBundle\Contract\SettingsWriterInterface;
use Quorae\SettingsBundle\Dto\SettingFieldDefinition;
use Quorae\SettingsBundle\Dto\UpdateSettingsResult;
use Quorae\SettingsBundle\Service\ConstraintFactory;
use Quorae\SettingsBundle\Service\SettingsFieldParser;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class UpdateSettingsHandler
{
    public function __construct(
        private SettingsReaderInterface $settingsReader,
        private SettingsWriterInterface $settingsWriter,
        private SettingsDefinitionProviderInterface $definitionProvider,
        private ValidatorInterface $validator,
        private ConstraintFactory $constraintFactory,
        private SettingsFieldParser $fieldParser,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @param array<string, mixed> $values
     */
    public function handle(string $group, array $values): UpdateSettingsResult
    {
        $definition = $this->definitionProvider->getDefinition($group);
        $fields = $this->fieldParser->parse($definition);

        $errorsByField = [];
        foreach ($values as $key => $value) {
            if (!\is_string($key) || !isset($fields[$key])) {
                continue;
            }

            $constraints = $this->buildConstraintsForField($fields[$key]);
            if ([] === $constraints) {
                continue;
            }

            $violations = $this->validator->validate($value, $constraints);
            if (0 === \count($violations)) {
                continue;
            }

            $messages = [];
            foreach ($violations as $violation) {
                $messages[] = (string) $violation->getMessage();
            }
            $errorsByField[$key] = $messages;
        }

        if ([] !== $errorsByField) {
            $this->logger->info('Settings update rejected', [
                'group' => $group,
                'invalid_keys' => array_keys($errorsByField),
            ]);

            return UpdateSettingsResult::invalid($errorsByField);
        }

        $this->settingsWriter->save($group, $this->normalizeValues($values, $fields));

        return UpdateSettingsResult::valid($this->settingsReader->getGroup($group));
    }

    /**
     * @param array<string, mixed>                  $values
     * @param array<string, SettingFieldDefinition> $fields
     *
     * @return array<string, mixed>
     */
    private function normalizeValues(array $values, array $fields): array
    {
        foreach ($values as $key => $value) {
            if (isset($fields[$key]) && 'boolean' === $fields[$key]->type && null === $value) {
                $values[$key] = false;
            }
        }

        return $values;
    }

    /**
     * @return list<Constraint>
     */
    private function buildConstraintsForField(SettingFieldDefinition $field): array
    {
        $explicit = $this->constraintFactory->build($field->rules);
        $implicit = $this->buildImplicitConstraintsForField($field);

        return array_values(array_merge($explicit, $implicit));
    }

    /**
     * @return list<Constraint>
     */
    private function buildImplicitConstraintsForField(SettingFieldDefinition $field): array
    {
        return match ($field->type) {
            'choice' => [] === $field->choices
                ? []
                : [new Choice(choices: array_keys($field->choices))],
            'integer' => [new Regex(pattern: '/^-?\d+$/', message: 'This value must be an integer.')],
            'float' => [new Regex(pattern: '/^-?\d+(\.\d+)?$/', message: 'This value must be a number.')],
            'boolean' => [new Callback(static function (mixed $v, ExecutionContextInterface $ctx): void {
                if (null === $v || \is_bool($v)) {
                    return;
                }
                if (\in_array($v, ['0', '1', 'true', 'false'], true)) {
                    return;
                }
                $ctx->buildViolation('This value must be a boolean.')->addViolation();
            })],
            default => [],
        };
    }
}
