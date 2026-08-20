<?php

declare(strict_types=1);

namespace Qubus\Tests\Cache;

use DateInterval;
use PHPUnit\Framework\TestCase;
use Qubus\Cache\Adapter\InMemoryCacheAdapter;
use Qubus\Cache\InMemoryCache;
use Qubus\Cache\Psr16\SimpleCache;

use function sleep;

class RegressionTest extends TestCase
{
    public function testDefaultTtlIsApplied(): void
    {
        $cache = new InMemoryCache(1);
        $cache->set('key', 'value');

        sleep(2);

        $this->assertFalse($cache->has('key'));
    }

    public function testExplicitOneSecondTtlOverridesDefault(): void
    {
        $cache = new InMemoryCache(30);
        $cache->set('key', 'value', 1);

        sleep(2);

        $this->assertFalse($cache->has('key'));
    }

    public function testInvertedDateIntervalExpiresImmediately(): void
    {
        $cache = new InMemoryCache();
        $interval = new DateInterval('PT1S');
        $interval->invert = 1;

        $this->assertTrue($cache->set('key', 'value', $interval));
        $this->assertFalse($cache->has('key'));
    }

    public function testKeyedGeneratorWorksWithSetMultiple(): void
    {
        $cache = new InMemoryCache();
        $values = (static function (): iterable {
            yield 'first' => 1;
            yield 'second' => 2;
        })();

        $this->assertTrue($cache->setMultiple($values));
        $this->assertSame(['first' => 1, 'second' => 2], $cache->getMultiple(['first', 'second']));
    }

    public function testPsr6FalseAndNullValuesAreCacheHits(): void
    {
        $cache = new InMemoryCache();
        $falseItem = $cache->getItem('false');
        $nullItem = $cache->getItem('null');
        $cache->save($falseItem->set(false));
        $cache->save($nullItem->set(null));

        $this->assertTrue($cache->getItem('false')->isHit());
        $this->assertFalse($cache->getItem('false')->get());
        $this->assertTrue($cache->getItem('null')->isHit());
        $this->assertNull($cache->getItem('null')->get());
    }

    public function testSha1ShapedKeyDoesNotCollideWithOriginalKey(): void
    {
        $cache = new InMemoryCache();
        $sha1ShapedKey = sha1('original');

        $cache->set('original', 'first');
        $cache->set($sha1ShapedKey, 'second');

        $this->assertSame('first', $cache->get('original'));
        $this->assertSame('second', $cache->get($sha1ShapedKey));
    }

    public function testUnsafeNamespacesRemainIsolated(): void
    {
        $adapter = new InMemoryCacheAdapter();
        $first = new SimpleCache($adapter, namespace: 'tenant.*');
        $second = new SimpleCache($adapter, namespace: 'tenantXX');
        $first->set('key', 'first');
        $second->set('key', 'second');

        $first->clear();

        $this->assertNull($first->get('key'));
        $this->assertSame('second', $second->get('key'));
    }
}
