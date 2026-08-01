<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use Quorae\SettingsBundle\Contract\SettingOverrideRepositoryInterface;
use Quorae\SettingsBundle\Service\SettingCryptor;
use Quorae\SettingsBundle\Service\SettingsCaster;
use Quorae\SettingsBundle\Service\SettingsDefinitionLoader;
use Quorae\SettingsBundle\Service\SettingsFieldParser;
use Quorae\SettingsBundle\Service\SettingsManager;
use Quorae\SettingsBundle\Service\SettingsSerializer;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

/**
 * The masked sentinel ('********') is what the admin UI submits back for a secret it displayed
 * without ever revealing: it means "keep the current value".
 *
 * Regression guard: `save()` used to mark the key as touched BEFORE testing for the sentinel, so
 * the key landed in `$keysToDelete` (touched, yet absent from `$toSave`) and the echo ERASED the
 * very secret it was meant to preserve. Re-saving a settings group from the console therefore
 * revoked every secret in it.
 */
final class SettingsManagerMaskedSentinelTest extends TestCase
{
    public function testMaskedSentinelNeitherSavesNorDeletesTheSecret(): void
    {
        $overrides = $this->createMock(SettingOverrideRepositoryInterface::class);

        // The sentinel key must be absent from BOTH sides: nothing written, nothing revoked.
        $overrides->expects(self::once())
            ->method('setMany')
            ->with('integration', ['retry_count' => '8'], 'global');
        $overrides->expects(self::once())
            ->method('deleteMany')
            ->with('integration', [], 'global');

        $this->managerFor($overrides)->save('integration', [
            'api_secret' => '********',
            'retry_count' => 8,
        ]);
    }

    public function testANonSentinelValueStillOverwritesTheSecret(): void
    {
        $overrides = $this->createMock(SettingOverrideRepositoryInterface::class);

        $overrides->expects(self::once())
            ->method('setMany')
            ->with(
                'integration',
                self::callback(static fn (array $values): bool => isset($values['api_secret'])
                    && '' !== $values['api_secret']
                    && '********' !== $values['api_secret']),
                'global',
            );
        $overrides->expects(self::once())->method('deleteMany')->with('integration', [], 'global');

        $this->managerFor($overrides)->save('integration', ['api_secret' => 'brand-new-secret']);
    }

    public function testAKeySubmittedAtItsDefaultIsStillRevoked(): void
    {
        $overrides = $this->createMock(SettingOverrideRepositoryInterface::class);

        // Guard against over-correcting: only the SENTINEL is exempt from deletion. A key
        // explicitly submitted at its default value must still drop its override.
        $overrides->expects(self::once())->method('setMany')->with('integration', [], 'global');
        $overrides->expects(self::once())->method('deleteMany')->with('integration', ['retry_count'], 'global');

        $this->managerFor($overrides)->save('integration', ['retry_count' => 3]);
    }

    private function managerFor(SettingOverrideRepositoryInterface $overrides): SettingsManager
    {
        $definitions = <<<'YAML'
            group: integration
            label: Integration
            fields:
                api_secret:
                    type: password
                    encrypted: true
                retry_count:
                    type: integer
                    default: 3
            YAML;

        $dir = sys_get_temp_dir() . '/quorae-settings-' . bin2hex(random_bytes(6));
        mkdir($dir . '/config/settings', 0o777, true);
        file_put_contents($dir . '/config/settings/integration.yaml', $definitions);

        $loader = new SettingsDefinitionLoader($dir . '/config/settings', $dir);

        return new SettingsManager(
            loader: $loader,
            overrides: $overrides,
            caster: new SettingsCaster(),
            serializer: new SettingsSerializer(),
            cryptor: new SettingCryptor(str_repeat('k', 32)),
            fieldParser: new SettingsFieldParser(),
            cache: new TagAwareAdapter(new ArrayAdapter()),
        );
    }
}
