<?php

namespace Articulate\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('articulate');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('connection')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('dsn')->defaultValue('%env(resolve:DATABASE_URL)%')->end()
                        ->scalarNode('user')->defaultValue('')->end()
                        ->scalarNode('password')->defaultValue('')->end()
                        ->booleanNode('persistent')->defaultFalse()->end()
                    ->end()
                ->end()
                ->arrayNode('paths')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('entities')->defaultValue('%kernel.project_dir%/src/Entity')->end()
                        ->scalarNode('migrations')->defaultValue('%kernel.project_dir%/migrations/Articulate')->end()
                        ->scalarNode('migrations_namespace')->defaultValue('App\\Migrations\\Articulate')->end()
                    ->end()
                ->end()
                ->arrayNode('cache')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('result')->defaultNull()->end()
                        ->scalarNode('statement')->defaultNull()->end()
                        ->scalarNode('second_level')->defaultNull()->end()
                        ->integerNode('second_level_ttl')->defaultValue(3600)->min(0)->end()
                    ->end()
                ->end()
                ->arrayNode('logging')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
