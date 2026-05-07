<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Twig\Component;

use Quorae\SettingsBundle\Contract\SettingsDefinitionProviderInterface;
use Quorae\SettingsBundle\Contract\SettingsReaderInterface;
use Quorae\SettingsBundle\Contract\SettingsWriterInterface;
use Quorae\SettingsBundle\Dto\SettingFieldDefinition;
use Quorae\SettingsBundle\Dto\SettingsGroup;
use Quorae\SettingsBundle\Handler\UpdateSettingsHandler;
use Quorae\SettingsBundle\Service\SettingsDisplayHelper;
use Quorae\SettingsBundle\Service\SettingsFieldParser;
use Quorae\SettingsBundle\Service\SettingsSerializer;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(
    name: 'QuoraeSettings:Editor',
    template: '@QuoraeSettings/components/QuoraeSettings/SettingsEditor.html.twig',
)]
class SettingsEditor
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $group = '';

    /** @var array<string, mixed> */
    #[LiveProp(writable: true)]
    public array $values = [];

    /** @var array<string, list<string>> */
    #[LiveProp]
    public array $errorsByField = [];

    #[LiveProp]
    public ?string $flashMessage = null;

    #[LiveProp]
    public ?string $flashType = null;

    public function __construct(
        private readonly SettingsReaderInterface $settingsReader,
        private readonly SettingsWriterInterface $settingsWriter,
        private readonly SettingsDefinitionProviderInterface $definitionProvider,
        private readonly UpdateSettingsHandler $updateHandler,
        private readonly SettingsFieldParser $fieldParser,
        private readonly SettingsDisplayHelper $displayHelper,
    ) {
    }

    public function mount(string $group): void
    {
        $this->group = $group;
        $this->values = $this->buildDisplayValues($this->settingsReader->getGroup($group));
    }

    public function getGroupData(): SettingsGroup
    {
        return $this->settingsReader->getGroup($this->group);
    }

    /**
     * @return array<string, SettingFieldDefinition>
     */
    public function getFieldDefinitions(): array
    {
        $definition = $this->definitionProvider->getDefinition($this->group);

        return $this->fieldParser->parse($definition);
    }

    /**
     * @return list<string>
     */
    public function getOverriddenKeys(): array
    {
        return $this->definitionProvider->getOverriddenKeys($this->group);
    }

    public function isOverridden(string $key): bool
    {
        return \in_array($key, $this->getOverriddenKeys(), true);
    }

    #[LiveAction]
    public function save(): void
    {
        $result = $this->updateHandler->handle($this->group, $this->filteredValues());

        if ($result->valid) {
            \assert(null !== $result->freshGroup);
            $this->errorsByField = [];
            $this->flashMessage = 'Settings saved.';
            $this->flashType = 'success';
            $this->values = $this->buildDisplayValues($result->freshGroup);

            return;
        }

        $this->errorsByField = $result->errorsByField;
        $this->flashMessage = null;
        $this->flashType = null;
        $this->values = $this->coerceToScalar($this->values);
    }

    #[LiveAction]
    public function resetField(#[LiveArg] string $key): void
    {
        $this->settingsWriter->resetToDefault($this->group, $key);

        $fresh = $this->settingsReader->getGroup($this->group);
        $fields = $this->getFieldDefinitions();
        $field = $fields[$key] ?? null;

        if (null === $field) {
            return;
        }

        $this->values[$key] = $this->displayHelper->maskIfSensitive($field, $fresh->all()[$key] ?? null);
        unset($this->errorsByField[$key]);

        $this->flashMessage = \sprintf('"%s" reset to default.', $field->label);
        $this->flashType = 'success';
    }

    /**
     * @return array<string, mixed>
     */
    private function filteredValues(): array
    {
        $fields = $this->getFieldDefinitions();
        $filtered = [];
        foreach ($this->values as $key => $value) {
            $field = $fields[$key] ?? null;
            if (null !== $field && $this->displayHelper->shouldMask($field) && SettingsSerializer::MASKED_SENTINEL === $value) {
                continue;
            }
            $filtered[$key] = $value;
        }

        return $filtered;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDisplayValues(SettingsGroup $group): array
    {
        $fields = $this->getFieldDefinitions();
        $values = [];
        foreach ($group->all() as $key => $value) {
            $field = $fields[$key] ?? null;
            $values[$key] = null === $field ? $value : $this->displayHelper->maskIfSensitive($field, $value);
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function coerceToScalar(array $values): array
    {
        $coerced = [];
        foreach ($values as $key => $value) {
            if (\is_scalar($value) || null === $value) {
                $coerced[$key] = $value;

                continue;
            }

            $coerced[$key] = json_encode($value, \JSON_THROW_ON_ERROR);
        }

        return $coerced;
    }
}
