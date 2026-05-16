<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\DependencyInjection;

use Quorae\SettingsBundle\Command\SettingsCacheCommand;
use Quorae\SettingsBundle\Command\SettingsCheckEncryptionCommand;
use Quorae\SettingsBundle\Command\SettingsClearCommand;
use Quorae\SettingsBundle\Command\SettingsListCommand;
use Quorae\SettingsBundle\Contract\SettingOverrideRepositoryInterface;
use Quorae\SettingsBundle\Contract\SettingsDefinitionProviderInterface;
use Quorae\SettingsBundle\Contract\SettingsReaderInterface;
use Quorae\SettingsBundle\Contract\SettingsWriterInterface;
use Quorae\SettingsBundle\Handler\IndexSettingsHandler;
use Quorae\SettingsBundle\Handler\UpdateSettingsHandler;
use Quorae\SettingsBundle\Repository\SettingOverrideRepository;
use Quorae\SettingsBundle\Service\ConstraintFactory;
use Quorae\SettingsBundle\Service\SettingCryptor;
use Quorae\SettingsBundle\Service\SettingsCaster;
use Quorae\SettingsBundle\Service\SettingsDefinitionLoader;
use Quorae\SettingsBundle\Service\SettingsDisplayHelper;
use Quorae\SettingsBundle\Service\SettingsEncryptionVerifier;
use Quorae\SettingsBundle\Service\SettingsFieldParser;
use Quorae\SettingsBundle\Service\SettingsManager;
use Quorae\SettingsBundle\Service\SettingsSerializer;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

final class QuoraeSettingsExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('doctrine')) {
            return;
        }

        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'QuoraeSettingsBundle' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => \dirname(__DIR__) . '/Entity',
                        'prefix' => 'Quorae\\SettingsBundle\\Entity',
                        'alias' => 'QuoraeSettings',
                    ],
                ],
            ],
        ]);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->registerLoader($container, $config);
        $this->registerCryptor($container, $config);
        $this->registerServices($container, $config);
        $this->registerManager($container, $config);
        $this->registerHandlers($container, $config);
        $this->registerCommands($container);
        $this->registerRepository($container);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function registerLoader(ContainerBuilder $container, array $config): void
    {
        $definition = new Definition(SettingsDefinitionLoader::class);
        $definition->setArguments([
            '%kernel.project_dir%/'.$config['definitions_path'],
            '%kernel.project_dir%',
            $config['allowed_env_prefixes'],
            $config['allowed_file_prefixes'],
        ]);
        $container->setDefinition(SettingsDefinitionLoader::class, $definition);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function registerCryptor(ContainerBuilder $container, array $config): void
    {
        $definition = new Definition(SettingCryptor::class);
        $definition->setArguments([
            '%kernel.secret%',
            $config['encryption']['hkdf_info'],
        ]);
        $container->setDefinition(SettingCryptor::class, $definition);
    }

    private function registerServices(ContainerBuilder $container, array $config): void
    {
        foreach ([
            SettingsCaster::class,
            SettingsSerializer::class,
            SettingsFieldParser::class,
            SettingsDisplayHelper::class,
            ConstraintFactory::class,
        ] as $class) {
            $container->setDefinition($class, (new Definition($class))->setAutowired(true));
        }

        $verifier = new Definition(SettingsEncryptionVerifier::class);
        $verifier->setArguments([
            new Reference(SettingsDefinitionProviderInterface::class),
            new Reference(SettingOverrideRepositoryInterface::class),
            new Reference(SettingCryptor::class),
            new Reference(SettingsFieldParser::class),
            $config['scope'],
        ]);
        $container->setDefinition(SettingsEncryptionVerifier::class, $verifier);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function registerManager(ContainerBuilder $container, array $config): void
    {
        $definition = new Definition(SettingsManager::class);
        $definition->setArguments([
            new Reference(SettingsDefinitionLoader::class),
            new Reference(SettingOverrideRepositoryInterface::class),
            new Reference(SettingsCaster::class),
            new Reference(SettingsSerializer::class),
            new Reference(SettingCryptor::class),
            new Reference(SettingsFieldParser::class),
            new Reference('cache.app.taggable'),
            $config['scope'],
        ]);

        $container->setDefinition(SettingsManager::class, $definition);
        $container->setAlias(SettingsReaderInterface::class, SettingsManager::class)->setPublic(true);
        $container->setAlias(SettingsWriterInterface::class, SettingsManager::class)->setPublic(true);
        $container->setAlias(SettingsDefinitionProviderInterface::class, SettingsManager::class)->setPublic(true);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function registerHandlers(ContainerBuilder $container, array $config): void
    {
        $update = new Definition(UpdateSettingsHandler::class);
        $update->setAutowired(true);
        $container->setDefinition(UpdateSettingsHandler::class, $update);

        $index = new Definition(IndexSettingsHandler::class);
        $index->setArguments([
            new Reference(SettingsDefinitionProviderInterface::class),
            new Reference(SettingsFieldParser::class),
            $config['scope'],
        ]);
        $container->setDefinition(IndexSettingsHandler::class, $index);
    }

    private function registerCommands(ContainerBuilder $container): void
    {
        foreach ([
            SettingsCacheCommand::class,
            SettingsClearCommand::class,
            SettingsListCommand::class,
            SettingsCheckEncryptionCommand::class,
        ] as $class) {
            $definition = new Definition($class);
            $definition->setAutowired(true);
            $definition->addTag('console.command');
            $container->setDefinition($class, $definition);
        }
    }

    private function registerRepository(ContainerBuilder $container): void
    {
        $definition = new Definition(SettingOverrideRepository::class);
        $definition->setAutowired(true);
        $container->setDefinition(SettingOverrideRepository::class, $definition);
        $container->setAlias(SettingOverrideRepositoryInterface::class, SettingOverrideRepository::class);
    }
}
