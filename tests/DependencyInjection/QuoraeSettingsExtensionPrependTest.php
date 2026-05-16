<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Quorae\SettingsBundle\DependencyInjection\QuoraeSettingsExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

final class QuoraeSettingsExtensionPrependTest extends TestCase
{
    public function testImplementsPrependExtensionInterface(): void
    {
        $extension = new QuoraeSettingsExtension();

        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
    }

    public function testPrependRegistersDoctrineEntityMapping(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'doctrine';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });

        $extension = new QuoraeSettingsExtension();
        $extension->prepend($container);

        $doctrineConfig = $container->getExtensionConfig('doctrine');
        self::assertNotEmpty($doctrineConfig);

        $mappings = $doctrineConfig[0]['orm']['mappings'] ?? [];
        self::assertArrayHasKey('QuoraeSettingsBundle', $mappings);

        $mapping = $mappings['QuoraeSettingsBundle'];
        self::assertSame('attribute', $mapping['type']);
        self::assertSame(false, $mapping['is_bundle']);
        self::assertSame('Quorae\\SettingsBundle\\Entity', $mapping['prefix']);
        self::assertSame('QuoraeSettings', $mapping['alias']);
        self::assertStringEndsWith('/Entity', $mapping['dir']);
    }

    public function testPrependSkipsWhenDoctrineNotRegistered(): void
    {
        $container = new ContainerBuilder();

        $extension = new QuoraeSettingsExtension();
        $extension->prepend($container);

        $doctrineConfig = $container->getExtensionConfig('doctrine');
        self::assertEmpty($doctrineConfig);
    }
}
