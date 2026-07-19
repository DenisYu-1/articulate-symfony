<?php

namespace Articulate\Symfony\Tests\DependencyInjection;

use Articulate\Connection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\EntityManager\RepositoryFactoryInterface;
use Articulate\Schema\EntityMetadataRegistry;
use Articulate\Symfony\ArticulateSymfonyBundle;
use Articulate\Symfony\DependencyInjection\ArticulateExtension;
use Articulate\Symfony\DependencyInjection\Compiler\RepositoryPass;
use Articulate\Symfony\Repository\ContainerRepositoryFactory;
use Articulate\Symfony\Repository\EntityManagerFactory;
use Articulate\Symfony\Tests\Fixtures\Repository\BookRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class ArticulateExtensionTest extends TestCase
{
    public function testBundleExposesArticulateConfigExtension(): void
    {
        $extension = (new ArticulateSymfonyBundle())->getContainerExtension();

        self::assertInstanceOf(ArticulateExtension::class, $extension);
        self::assertSame('articulate', $extension->getAlias());
    }

    public function testServicesAndCommandsAreRegistered(): void
    {
        $container = $this->createContainer();

        $extension = new ArticulateExtension();
        $extension->load([[
            'connection' => [
                'dsn' => 'mysql:host=127.0.0.1;dbname=app',
                'user' => 'app',
                'password' => 'secret',
            ],
            'paths' => [
                'entities' => ['/app/src/Entity', '/app/src/OtherEntity'],
                'migrations' => '/app/migrations',
                'migrations_namespace' => 'App\\Migrations',
            ],
        ]], $container);

        self::assertTrue($container->hasDefinition('articulate.connection'));
        self::assertTrue($container->hasAlias(Connection::class));
        self::assertTrue($container->hasDefinition('articulate.entity_manager'));
        self::assertTrue($container->hasAlias(EntityManager::class));
        self::assertTrue($container->getAlias(EntityManager::class)->isPublic());
        self::assertTrue($container->hasDefinition('articulate.repository_factory'));
        self::assertTrue($container->hasAlias(RepositoryFactoryInterface::class));
        self::assertTrue($container->hasDefinition('articulate.metadata_registry'));
        self::assertTrue($container->hasAlias(EntityMetadataRegistry::class));

        $entityManagerDefinition = $container->getDefinition('articulate.entity_manager');
        self::assertSame([EntityManagerFactory::class, 'create'], $entityManagerDefinition->getFactory());

        foreach (['init', 'diff', 'migrate', 'validate', 'warm_metadata_cache'] as $command) {
            self::assertTrue($container->hasDefinition(sprintf('articulate.command.%s', $command)));
            self::assertTrue($container->getDefinition(sprintf('articulate.command.%s', $command))->hasTag('console.command'));
        }

        $expectedPaths = ['/app/src/Entity', '/app/src/OtherEntity'];
        self::assertSame($expectedPaths, $container->getDefinition('articulate.command.diff')->getArgument(3));
        self::assertSame($expectedPaths, $container->getDefinition('articulate.command.migrate')->getArgument(4));
        self::assertSame($expectedPaths, $container->getDefinition('articulate.command.validate')->getArgument(1));
        self::assertSame($expectedPaths, $container->getDefinition('articulate.command.warm_metadata_cache')->getArgument(1));
    }

    public function testSingleEntitiesPathStringIsNormalizedToArray(): void
    {
        $container = $this->createContainer();

        $extension = new ArticulateExtension();
        $extension->load([[
            'paths' => [
                'entities' => '/app/src/Entity',
            ],
        ]], $container);

        self::assertSame(
            ['/app/src/Entity'],
            $container->getDefinition('articulate.command.diff')->getArgument(3),
        );
    }

    public function testTaggedRepositoriesAreAddedToLocator(): void
    {
        $container = $this->createContainer();
        (new ArticulateExtension())->load([], $container);

        $container
            ->setDefinition('app.book_repository', new Definition(BookRepository::class))
            ->addTag('articulate.repository');

        (new RepositoryPass())->process($container);

        $repositoryFactoryDefinition = $container->getDefinition('articulate.repository_factory');
        self::assertSame(ContainerRepositoryFactory::class, $repositoryFactoryDefinition->getClass());

        $locatorArgument = $repositoryFactoryDefinition->getArgument(1);
        self::assertTrue(method_exists($locatorArgument, 'getValues'));
        self::assertArrayHasKey(BookRepository::class, $locatorArgument->getValues());
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', '/app');
        $container->setDefinition('logger', new Definition(NullLogger::class));

        return $container;
    }
}
