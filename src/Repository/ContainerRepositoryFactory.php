<?php

namespace Articulate\Symfony\Repository;

use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\EntityManager\RepositoryFactoryInterface;
use Articulate\Modules\Repository\AbstractRepository;
use Articulate\Modules\Repository\EntityRepository;
use Articulate\Modules\Repository\Exceptions\RepositoryException;
use Articulate\Modules\Repository\RepositoryInterface;
use Psr\Container\ContainerInterface;

final class ContainerRepositoryFactory implements RepositoryFactoryInterface
{
    /** @var array<string, RepositoryInterface> */
    private array $repositories = [];

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ContainerInterface $repositoryLocator,
    ) {
    }

    public function getRepository(string $entityClass): object
    {
        if (isset($this->repositories[$entityClass])) {
            return $this->repositories[$entityClass];
        }

        $metadata = $this->entityManager->getMetadataRegistry()->getMetadata($entityClass);
        $repositoryClass = $metadata->getRepositoryClass() ?? EntityRepository::class;

        if ($this->repositoryLocator->has($repositoryClass)) {
            $repository = $this->repositoryLocator->get($repositoryClass);
            $this->assertRepository($repository, $repositoryClass);

            return $this->repositories[$entityClass] = $repository;
        }

        $this->assertRepositoryClass($repositoryClass);

        return $this->repositories[$entityClass] = new $repositoryClass($this->entityManager, $entityClass);
    }

    private function assertRepository(object $repository, string $repositoryClass): void
    {
        if (!$repository instanceof RepositoryInterface) {
            throw new RepositoryException(sprintf('Repository service "%s" must implement %s.', $repositoryClass, RepositoryInterface::class));
        }
    }

    private function assertRepositoryClass(string $repositoryClass): void
    {
        if (!class_exists($repositoryClass)) {
            throw new RepositoryException(sprintf('Repository class "%s" does not exist.', $repositoryClass));
        }

        if (!is_subclass_of($repositoryClass, RepositoryInterface::class)) {
            throw new RepositoryException(sprintf('Repository class "%s" must implement %s.', $repositoryClass, RepositoryInterface::class));
        }

        if (!is_subclass_of($repositoryClass, AbstractRepository::class)) {
            throw new RepositoryException(sprintf('Repository class "%s" must extend %s.', $repositoryClass, AbstractRepository::class));
        }
    }
}
