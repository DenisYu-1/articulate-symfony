<?php

namespace Articulate\Symfony\Repository;

use Articulate\Connection;
use Articulate\Modules\EntityManager\ChangeTrackingStrategy;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\EntityManager\QueryExecutor;
use Articulate\Modules\EntityManager\UpdateConflictResolutionStrategy;
use Articulate\Modules\Generators\GeneratorRegistry;
use Articulate\Schema\EntityMetadataRegistry;
use Articulate\Schema\HydratorInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class EntityManagerFactory
{
    public static function create(
        Connection $connection,
        ContainerInterface $repositoryLocator,
        ?ChangeTrackingStrategy $changeTrackingStrategy = null,
        ?HydratorInterface $hydrator = null,
        ?GeneratorRegistry $generatorRegistry = null,
        ?EntityMetadataRegistry $metadataRegistry = null,
        ?QueryExecutor $queryExecutor = null,
        ?UpdateConflictResolutionStrategy $updateConflictResolutionStrategy = null,
        ?CacheItemPoolInterface $resultCache = null,
        ?CacheItemPoolInterface $statementCache = null,
        ?CacheItemPoolInterface $secondLevelCache = null,
        int $secondLevelCacheTtl = 3600,
        ?LoggerInterface $logger = null,
    ): EntityManager {
        $entityManager = new EntityManager(
            $connection,
            $changeTrackingStrategy,
            $hydrator,
            $generatorRegistry,
            $metadataRegistry,
            $queryExecutor,
            $updateConflictResolutionStrategy,
            $resultCache,
            null,
            $statementCache,
            $secondLevelCache,
            $secondLevelCacheTtl,
            $logger,
        );

        $entityManager->setRepositoryFactory(new ContainerRepositoryFactory($entityManager, $repositoryLocator));

        return $entityManager;
    }

}
