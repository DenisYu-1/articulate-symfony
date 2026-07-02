<?php

namespace Articulate\Symfony\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class RepositoryPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('articulate.entity_manager')) {
            return;
        }

        $repositories = [];
        foreach ($container->findTaggedServiceIds('articulate.repository') as $serviceId => $tags) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass();

            if ($class === null) {
                continue;
            }

            $repositories[$class] = new Reference($serviceId);
        }

        $repositoryLocator = new ServiceLocatorArgument($repositories);

        $container->getDefinition('articulate.entity_manager')->replaceArgument(1, $repositoryLocator);

        if ($container->hasDefinition('articulate.repository_factory')) {
            $container->getDefinition('articulate.repository_factory')->replaceArgument(1, $repositoryLocator);
        }
    }
}
