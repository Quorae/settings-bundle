<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('quorae_settings');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('definitions_path')
                    ->defaultValue('config/settings')
                    ->info('Relative path from project root to the YAML definitions directory.')
                ->end()
                ->scalarNode('scope')
                    ->defaultValue('global')
                    ->info('The only allowed scope. Multi-scope support is a future extension point.')
                ->end()
                ->arrayNode('allowed_env_prefixes')
                    ->defaultValue([])
                    ->scalarPrototype()->end()
                    ->info('Env var prefixes allowed in YAML `env_key:` fields. Empty = all allowed.')
                ->end()
                ->arrayNode('allowed_file_prefixes')
                    ->defaultValue([])
                    ->scalarPrototype()->end()
                    ->info('Relative path prefixes allowed for `file://` defaults. Empty = all allowed (with path traversal guard).')
                ->end()
                ->arrayNode('encryption')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('hkdf_info')
                            ->defaultValue('quorae-settings-encryption')
                            ->info('HKDF info context string for key derivation. Change this to isolate encryption keys between apps sharing the same APP_SECRET.')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
