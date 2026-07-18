<?php

namespace Articulate\Symfony\Tests\DependencyInjection;

use Articulate\Symfony\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testDefaultConfigurationIsNormalized(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), []);

        self::assertSame('%env(resolve:DATABASE_URL)%', $config['connection']['dsn']);
        self::assertSame('', $config['connection']['user']);
        self::assertSame('', $config['connection']['password']);
        self::assertFalse($config['connection']['persistent']);
        self::assertSame(['%kernel.project_dir%/src/Entity'], $config['paths']['entities']);
        self::assertSame('%kernel.project_dir%/migrations/Articulate', $config['paths']['migrations']);
        self::assertSame('App\\Migrations\\Articulate', $config['paths']['migrations_namespace']);
        self::assertNull($config['cache']['result']);
        self::assertSame(3600, $config['cache']['second_level_ttl']);
        self::assertFalse($config['logging']['enabled']);
    }

    public function testCustomConfigurationIsNormalized(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'connection' => [
                'dsn' => 'mysql:host=127.0.0.1;dbname=app',
                'user' => 'app',
                'password' => 'secret',
                'persistent' => true,
            ],
            'paths' => [
                'entities' => ['/app/src/Domain', '/app/src/OtherDomain'],
                'migrations' => '/app/migrations',
                'migrations_namespace' => 'App\\Migrations',
            ],
            'cache' => [
                'result' => 'cache.app',
                'statement' => 'cache.system',
                'second_level' => 'cache.articulate_entities',
                'second_level_ttl' => 60,
            ],
            'logging' => [
                'enabled' => true,
            ],
        ]]);

        self::assertSame('mysql:host=127.0.0.1;dbname=app', $config['connection']['dsn']);
        self::assertSame('app', $config['connection']['user']);
        self::assertSame('secret', $config['connection']['password']);
        self::assertTrue($config['connection']['persistent']);
        self::assertSame(['/app/src/Domain', '/app/src/OtherDomain'], $config['paths']['entities']);
        self::assertSame('/app/migrations', $config['paths']['migrations']);
        self::assertSame('App\\Migrations', $config['paths']['migrations_namespace']);
        self::assertSame('cache.app', $config['cache']['result']);
        self::assertSame('cache.system', $config['cache']['statement']);
        self::assertSame('cache.articulate_entities', $config['cache']['second_level']);
        self::assertSame(60, $config['cache']['second_level_ttl']);
        self::assertTrue($config['logging']['enabled']);
    }

    public function testSingleEntitiesPathStringIsNormalizedToArray(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'paths' => [
                'entities' => '/app/src/Domain',
            ],
        ]]);

        self::assertSame(['/app/src/Domain'], $config['paths']['entities']);
    }
}
