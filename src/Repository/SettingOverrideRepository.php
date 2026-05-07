<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Repository;

use Quorae\SettingsBundle\Contract\SettingOverrideRepositoryInterface;
use Quorae\SettingsBundle\Entity\SettingOverride;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SettingOverride>
 */
final class SettingOverrideRepository extends ServiceEntityRepository implements SettingOverrideRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SettingOverride::class);
    }

    public function getGroupOverrides(string $group, string $scope): array
    {
        /** @var list<array{fieldKey: string, value: ?string}> $rows */
        $rows = $this->createQueryBuilder('s')
            ->select('s.key AS fieldKey', 's.value AS value')
            ->andWhere('s.group = :group')
            ->andWhere('s.scope = :scope')
            ->setParameter('group', $group)
            ->setParameter('scope', $scope)
            ->getQuery()
            ->getScalarResult();

        $overrides = [];
        foreach ($rows as $row) {
            $overrides[$row['fieldKey']] = $row['value'];
        }

        return $overrides;
    }

    public function setMany(string $group, array $values, string $scope): void
    {
        if ([] === $values) {
            return;
        }

        $connection = $this->getEntityManager()->getConnection();
        $platform = $connection->getDatabasePlatform();

        if ($platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform) {
            $this->upsertPostgres($group, $values, $scope, $connection);
        } elseif ($platform instanceof \Doctrine\DBAL\Platforms\MySQLPlatform || $platform instanceof \Doctrine\DBAL\Platforms\MariaDBPlatform) {
            $this->upsertMysql($group, $values, $scope, $connection);
        } else {
            $this->upsertGeneric($group, $values, $scope, $connection);
        }
    }

    public function delete(string $group, string $key, string $scope): void
    {
        $this->createQueryBuilder('s')
            ->delete()
            ->andWhere('s.group = :group')
            ->andWhere('s.key = :key')
            ->andWhere('s.scope = :scope')
            ->setParameter('group', $group)
            ->setParameter('key', $key)
            ->setParameter('scope', $scope)
            ->getQuery()
            ->execute();
    }

    public function deleteMany(string $group, array $keys, string $scope): void
    {
        if ([] === $keys) {
            return;
        }

        $this->createQueryBuilder('s')
            ->delete()
            ->andWhere('s.group = :group')
            ->andWhere('s.key IN (:keys)')
            ->andWhere('s.scope = :scope')
            ->setParameter('group', $group)
            ->setParameter('keys', $keys)
            ->setParameter('scope', $scope)
            ->getQuery()
            ->execute();
    }

    public function deleteGroup(string $group, string $scope): void
    {
        $this->createQueryBuilder('s')
            ->delete()
            ->andWhere('s.group = :group')
            ->andWhere('s.scope = :scope')
            ->setParameter('group', $group)
            ->setParameter('scope', $scope)
            ->getQuery()
            ->execute();
    }

    /**
     * @param array<string, ?string> $values
     */
    private function upsertPostgres(string $group, array $values, string $scope, \Doctrine\DBAL\Connection $connection): void
    {
        $connection->beginTransaction();
        try {
            $sql = <<<'SQL'
                INSERT INTO setting_overrides (group_name, field_key, value, setting_scope, created_at, updated_at)
                VALUES (:group, :key, :value, :scope, NOW(), NOW())
                ON CONFLICT (group_name, field_key, setting_scope)
                DO UPDATE SET value = EXCLUDED.value, updated_at = NOW()
                SQL;

            foreach ($values as $key => $value) {
                $connection->executeStatement($sql, [
                    'group' => $group,
                    'key' => $key,
                    'value' => $value,
                    'scope' => $scope,
                ]);
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * @param array<string, ?string> $values
     */
    private function upsertMysql(string $group, array $values, string $scope, \Doctrine\DBAL\Connection $connection): void
    {
        $connection->beginTransaction();
        try {
            $sql = <<<'SQL'
                INSERT INTO setting_overrides (group_name, field_key, value, setting_scope, created_at, updated_at)
                VALUES (:group, :key, :value, :scope, NOW(), NOW())
                ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()
                SQL;

            foreach ($values as $key => $value) {
                $connection->executeStatement($sql, [
                    'group' => $group,
                    'key' => $key,
                    'value' => $value,
                    'scope' => $scope,
                ]);
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * @param array<string, ?string> $values
     */
    private function upsertGeneric(string $group, array $values, string $scope, \Doctrine\DBAL\Connection $connection): void
    {
        $connection->beginTransaction();
        try {
            foreach ($values as $key => $value) {
                $existing = $connection->fetchOne(
                    'SELECT id FROM setting_overrides WHERE group_name = :group AND field_key = :key AND setting_scope = :scope',
                    ['group' => $group, 'key' => $key, 'scope' => $scope],
                );

                if (false !== $existing) {
                    $connection->executeStatement(
                        'UPDATE setting_overrides SET value = :value, updated_at = :now WHERE id = :id',
                        ['value' => $value, 'now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'), 'id' => $existing],
                    );
                } else {
                    $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
                    $connection->executeStatement(
                        'INSERT INTO setting_overrides (group_name, field_key, value, setting_scope, created_at, updated_at) VALUES (:group, :key, :value, :scope, :now, :now2)',
                        ['group' => $group, 'key' => $key, 'value' => $value, 'scope' => $scope, 'now' => $now, 'now2' => $now],
                    );
                }
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }
}
