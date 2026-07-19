<?php

namespace Articulate\Symfony\DependencyInjection;

use Articulate\Commands\DiffCommand;
use Articulate\Commands\InitCommand;
use Articulate\Commands\MigrateCommand;
use Articulate\Commands\ValidateCommand;
use Articulate\Commands\WarmMetadataCacheCommand;
use Articulate\Connection;
use Articulate\Modules\Database\SchemaComparator\DatabaseSchemaComparator;
use Articulate\Modules\Database\SchemaReader\DatabaseSchemaReaderInterface;
use Articulate\Modules\Database\SchemaReader\SchemaReaderFactory;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\EntityManager\RepositoryFactoryInterface;
use Articulate\Modules\Migrations\Generator\MigrationsCommandGenerator;
use Articulate\QueryLogger\PsrQueryLogger;
use Articulate\QueryLogger\QueryLoggerInterface;
use Articulate\Schema\EntityMetadataRegistry;
use Articulate\Schema\SchemaNaming;
use Articulate\Symfony\Repository\ContainerRepositoryFactory;
use Articulate\Symfony\Repository\EntityManagerFactory;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface as SymfonyContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

final class ArticulateExtension extends Extension
{
    /**
     * @param array<int, array<string, mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        /** @var array{connection: array{dsn: string, user: string, password: string, persistent: bool}, paths: array{entities: list<string>, migrations: string, migrations_namespace: string}, cache: array{result: ?string, statement: ?string, second_level: ?string, second_level_ttl: int}, logging: array{enabled: bool}} $config */
        $config = $this->processConfiguration($configuration, $configs);

        $this->registerConnection($container, $config);
        $this->registerEntityManager($container, $config);
        $this->registerSchemaServices($container);
        $this->registerCommands($container, $config);
    }

    /**
     * @param array{connection: array{dsn: string, user: string, password: string, persistent: bool}, logging: array{enabled: bool}} $config
     */
    private function registerConnection(ContainerBuilder $container, array $config): void
    {
        if ($config['logging']['enabled']) {
            $container
                ->setDefinition('articulate.query_logger', new Definition(PsrQueryLogger::class))
                ->setArguments([new Reference('logger')])
                ->addTag('monolog.logger', ['channel' => 'articulate']);
            $container->setAlias(QueryLoggerInterface::class, new Alias('articulate.query_logger', false));
        }

        $connectionArguments = [
            $config['connection']['dsn'],
            $config['connection']['user'],
            $config['connection']['password'],
            $config['logging']['enabled'] ? new Reference('articulate.query_logger') : null,
            $config['connection']['persistent'],
        ];

        $container
            ->setDefinition('articulate.connection', new Definition(Connection::class))
            ->setArguments($connectionArguments)
            ->setPublic(false);

        $container->setAlias(Connection::class, new Alias('articulate.connection', false));
    }

    /**
     * @param array{cache: array{result: ?string, statement: ?string, second_level: ?string, second_level_ttl: int, metadata: ?string}} $config
     */
    private function registerEntityManager(ContainerBuilder $container, array $config): void
    {
        $container
            ->setDefinition('articulate.repository_factory', new Definition(ContainerRepositoryFactory::class))
            ->setArguments([
                new Reference('articulate.entity_manager'),
                new ServiceLocatorArgument([]),
            ])
            ->setPublic(false);
        $container->setAlias(RepositoryFactoryInterface::class, new Alias('articulate.repository_factory', false));

        $container
            ->setDefinition('articulate.metadata_registry', new Definition(EntityMetadataRegistry::class))
            ->setArguments([$this->optionalReference($config['cache']['metadata'])])
            ->setPublic(false);
        $container->setAlias(EntityMetadataRegistry::class, new Alias('articulate.metadata_registry', false));

        $entityManager = new Definition(EntityManager::class);
        $entityManager
            ->setFactory([EntityManagerFactory::class, 'create'])
            ->setArguments([
                new Reference('articulate.connection'),
                new ServiceLocatorArgument([]),
                null,
                null,
                null,
                new Reference('articulate.metadata_registry'),
                null,
                null,
                $this->optionalReference($config['cache']['result']),
                $this->optionalReference($config['cache']['statement']),
                $this->optionalReference($config['cache']['second_level']),
                $config['cache']['second_level_ttl'],
                new Reference('logger', SymfonyContainerInterface::IGNORE_ON_INVALID_REFERENCE),
            ])
            ->setPublic(false);

        $container->setDefinition('articulate.entity_manager', $entityManager);
        $container->setAlias(EntityManager::class, new Alias('articulate.entity_manager', true));
    }

    private function registerSchemaServices(ContainerBuilder $container): void
    {
        $container
            ->setDefinition('articulate.schema_reader', new Definition(DatabaseSchemaReaderInterface::class))
            ->setFactory([SchemaReaderFactory::class, 'create'])
            ->setArguments([new Reference('articulate.connection')])
            ->setPublic(false);

        $container->setAlias(DatabaseSchemaReaderInterface::class, new Alias('articulate.schema_reader', false));

        $container
            ->setDefinition('articulate.schema_naming', new Definition(SchemaNaming::class))
            ->setPublic(false);

        $container->setAlias(SchemaNaming::class, new Alias('articulate.schema_naming', false));

        $container
            ->setDefinition('articulate.schema_comparator', new Definition(DatabaseSchemaComparator::class))
            ->setArguments([
                new Reference('articulate.schema_reader'),
                new Reference('articulate.schema_naming'),
            ])
            ->setPublic(false);

        $container->setAlias(DatabaseSchemaComparator::class, new Alias('articulate.schema_comparator', false));

        $container
            ->setDefinition('articulate.migrations_command_generator', new Definition(MigrationsCommandGenerator::class))
            ->setArguments([new Reference('articulate.connection')])
            ->setPublic(false);

        $container->setAlias(MigrationsCommandGenerator::class, new Alias('articulate.migrations_command_generator', false));
    }

    /**
     * @param array{paths: array{entities: list<string>, migrations: string, migrations_namespace: string}} $config
     */
    private function registerCommands(ContainerBuilder $container, array $config): void
    {
        $container
            ->setDefinition('articulate.command.init', new Definition(InitCommand::class))
            ->setArguments([new Reference('articulate.connection')])
            ->addTag('console.command', ['command' => 'articulate:init']);

        $container
            ->setDefinition('articulate.command.diff', new Definition(DiffCommand::class))
            ->setArguments([
                new Reference('articulate.schema_comparator'),
                new Reference('articulate.migrations_command_generator'),
                $config['paths']['migrations'],
                $config['paths']['entities'],
                $config['paths']['migrations_namespace'],
            ])
            ->addTag('console.command', ['command' => 'articulate:diff']);

        $container
            ->setDefinition('articulate.command.migrate', new Definition(MigrateCommand::class))
            ->setArguments([
                new Reference('articulate.connection'),
                new Reference('articulate.command.init'),
                $config['paths']['migrations'],
                new Reference('articulate.schema_comparator'),
                $config['paths']['entities'],
            ])
            ->addTag('console.command', ['command' => 'articulate:migrate']);

        $container
            ->setDefinition('articulate.command.validate', new Definition(ValidateCommand::class))
            ->setArguments([
                new Reference('articulate.schema_comparator'),
                $config['paths']['entities'],
            ])
            ->addTag('console.command', ['command' => 'articulate:validate']);

        $container
            ->setDefinition('articulate.command.warm_metadata_cache', new Definition(WarmMetadataCacheCommand::class))
            ->setArguments([
                new Reference('articulate.metadata_registry'),
                $config['paths']['entities'],
            ])
            ->addTag('console.command', ['command' => 'articulate:warm-metadata-cache']);
    }

    private function optionalReference(?string $serviceId): mixed
    {
        if ($serviceId === null || $serviceId === '') {
            return null;
        }

        return new Reference($serviceId);
    }
}
