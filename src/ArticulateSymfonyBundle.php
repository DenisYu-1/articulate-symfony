<?php

namespace Articulate\Symfony;

use Articulate\Symfony\DependencyInjection\Compiler\RepositoryPass;
use Articulate\Symfony\DependencyInjection\ArticulateExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class ArticulateSymfonyBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return $this->extension ??= new ArticulateExtension();
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new RepositoryPass());
    }
}
