# Articulate Symfony Bundle

Symfony integration for the Articulate context-bounded ORM.

## Installation

```bash
composer require denisyu-1/articulate-symfony
```

If Symfony Flex does not enable the bundle automatically, add it manually:

```php
// config/bundles.php
return [
    Articulate\Symfony\ArticulateSymfonyBundle::class => ['all' => true],
];
```

## Configuration

```yaml
# config/packages/articulate.yaml
articulate:
  connection:
    dsn: '%env(resolve:DATABASE_URL)%'
    user: ''
    password: ''
    persistent: false

  paths:
    entities: '%kernel.project_dir%/src/Entity'
    migrations: '%kernel.project_dir%/migrations/Articulate'
    migrations_namespace: 'App\Migrations\Articulate'

  cache:
    result: cache.app
    statement: cache.system
    second_level: cache.app
    second_level_ttl: 3600

  logging:
    enabled: '%kernel.debug%'
```

## Usage

Inject the Articulate entity manager into your services:

```php
use Articulate\Modules\EntityManager\EntityManager;

final class RegisterBook
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    public function __invoke(Book $book): void
    {
        $this->entityManager->persist($book);
        $this->entityManager->flush();
    }
}
```

The bundle also exposes `Articulate\Connection` for lower-level database work.

## Commands

```bash
bin/console articulate:init
bin/console articulate:validate
bin/console articulate:diff
bin/console articulate:migrate
bin/console articulate:migrate --rollback
```

The command paths come from `articulate.paths.entities` and `articulate.paths.migrations`.

## Custom Repositories

Repository classes declared through Articulate's `#[Entity(repositoryClass: ...)]` attribute are created automatically. To let Symfony build a repository service, tag it:

```yaml
services:
  App\Repository\BookRepository:
    tags: ['articulate.repository']
```

Tagged repositories must implement `Articulate\Modules\Repository\RepositoryInterface`. Repositories extending Articulate's `AbstractRepository` continue to work without being registered as services.
