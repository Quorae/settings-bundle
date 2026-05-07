<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Entity;

use Quorae\SettingsBundle\Repository\SettingOverrideRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SettingOverrideRepository::class)]
#[ORM\Table(name: 'setting_overrides')]
#[ORM\UniqueConstraint(name: 'uniq_group_key_scope', columns: ['group_name', 'field_key', 'setting_scope'])]
#[ORM\Index(name: 'idx_group_scope', columns: ['group_name', 'setting_scope'])]
class SettingOverride
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => false])]
    private ?int $id = null;

    #[ORM\Column(name: 'group_name', type: Types::STRING, length: 80)]
    private string $group;

    #[ORM\Column(name: 'field_key', type: Types::STRING, length: 120)]
    private string $key;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $value;

    #[ORM\Column(name: 'setting_scope', type: Types::STRING, length: 20, options: ['default' => 'global'])]
    private string $scope;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $group,
        string $key,
        ?string $value,
        string $scope = 'global',
    ) {
        $this->group = $group;
        $this->key = $key;
        $this->value = $value;
        $this->scope = $scope;
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): void
    {
        $this->value = $value;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
