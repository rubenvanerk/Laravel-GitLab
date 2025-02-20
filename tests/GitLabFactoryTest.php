<?php

declare(strict_types=1);

/*
 * This file is part of Laravel GitLab.
 *
 * (c) Graham Campbell <hello@gjcampbell.co.uk>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GrahamCampbell\Tests\GitLab;

use Gitlab\Client;
use GrahamCampbell\BoundedCache\BoundedCacheInterface;
use GrahamCampbell\GitLab\Auth\AuthenticatorFactory;
use GrahamCampbell\GitLab\Cache\ConnectionFactory;
use GrahamCampbell\GitLab\GitLabFactory;
use GrahamCampbell\GitLab\HttpClient\BuilderFactory;
use GrahamCampbell\TestBench\AbstractTestCase as AbstractTestBenchTestCase;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory as GuzzlePsrFactory;
use Http\Client\Common\HttpMethodsClientInterface;
use Illuminate\Contracts\Cache\Factory;
use InvalidArgumentException;
use Mockery;

/**
 * This is the gitlab factory test class.
 *
 * @author Graham Campbell <hello@gjcampbell.co.uk>
 */
class GitLabFactoryTest extends AbstractTestBenchTestCase
{
    public function testMakeStandard()
    {
        $factory = $this->getFactory();

        $client = $factory[0]->make(['token' => 'your-token', 'method' => 'token']);

        $this->assertInstanceOf(Client::class, $client);
        $this->assertInstanceOf(HttpMethodsClientInterface::class, $client->getHttpClient());
    }

    public function testMakeStandardWithCache()
    {
        $factory = $this->getFactory();

        $boundedCache = Mockery::mock(BoundedCacheInterface::class);
        $boundedCache->shouldReceive('getMaximumLifetime')->once()->with()->andReturn(42);

        $factory[1]->shouldReceive('make')->once()->with(['name' => 'main', 'driver' => 'illuminate'])->andReturn($boundedCache);

        $client = $factory[0]->make(['token' => 'your-token', 'method' => 'token', 'cache' => ['name' => 'main', 'driver' => 'illuminate']]);

        $this->assertInstanceOf(Client::class, $client);
        $this->assertInstanceOf(HttpMethodsClientInterface::class, $client->getHttpClient());
    }

    public function testMakeStandardNamedCache()
    {
        $factory = $this->getFactory();

        $boundedCache = Mockery::mock(BoundedCacheInterface::class);
        $boundedCache->shouldReceive('getMaximumLifetime')->once()->with()->andReturn(42);

        $factory[1]->shouldReceive('make')->once()->with(['name' => 'main', 'driver' => 'illuminate', 'connection' => 'foo'])->andReturn($boundedCache);

        $client = $factory[0]->make(['token' => 'your-token', 'method' => 'token', 'cache' => ['name' => 'main', 'driver' => 'illuminate', 'connection' => 'foo']]);

        $this->assertInstanceOf(Client::class, $client);
        $this->assertInstanceOf(HttpMethodsClientInterface::class, $client->getHttpClient());
    }

    public function testMakeStandardNoCacheOrBackoff()
    {
        $factory = $this->getFactory();

        $client = $factory[0]->make(['token' => 'your-token', 'method' => 'token', 'cache' => false, 'backoff' => false]);

        $this->assertInstanceOf(Client::class, $client);
        $this->assertInstanceOf(HttpMethodsClientInterface::class, $client->getHttpClient());
    }

    public function testMakeStandardExplicitBackoff()
    {
        $factory = $this->getFactory();

        $client = $factory[0]->make(['token' => 'your-token', 'method' => 'token', 'backoff' => true]);

        $this->assertInstanceOf(Client::class, $client);
        $this->assertInstanceOf(HttpMethodsClientInterface::class, $client->getHttpClient());
    }

    public function testMakeStandardExplicitUrl()
    {
        $factory = $this->getFactory();

        $client = $factory[0]->make(['token' => 'your-token', 'method' => 'token', 'url' => 'https://api.example.com']);

        $this->assertInstanceOf(Client::class, $client);
        $this->assertInstanceOf(HttpMethodsClientInterface::class, $client->getHttpClient());
    }

    public function testMakeNoneMethod()
    {
        $factory = $this->getFactory();

        $client = $factory[0]->make(['method' => 'none']);

        $this->assertInstanceOf(Client::class, $client);
        $this->assertInstanceOf(HttpMethodsClientInterface::class, $client->getHttpClient());
    }

    public function testMakeInvalidMethod()
    {
        $factory = $this->getFactory();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported authentication method [bar].');

        $factory[0]->make(['method' => 'bar']);
    }

    public function testMakeEmpty()
    {
        $factory = $this->getFactory();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The gitlab factory requires an auth method.');

        $factory[0]->make([]);
    }

    protected function getFactory()
    {
        $psrFactory = new GuzzlePsrFactory();

        $builder = new BuilderFactory(
            new GuzzleClient(['connect_timeout' => 10, 'timeout' => 30]),
            $psrFactory,
            $psrFactory,
            $psrFactory,
        );

        $cache = Mockery::mock(ConnectionFactory::class);

        return [new GitLabFactory($builder, new AuthenticatorFactory(), $cache), $cache];
    }
}
