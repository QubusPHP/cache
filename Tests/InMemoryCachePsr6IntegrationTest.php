<?php

declare(strict_types=1);

namespace Qubus\Tests\Cache;

use Qubus\Cache\InMemoryCache;

class InMemoryCachePsr6IntegrationTest extends CachePoolTest
{
    /** @var array $skippedTests  */
    protected array $skippedTests = [
        'testSaveWithoutExpire'         => 'Need to investigate this test for in memory cache.',
        'testDeferredSaveWithoutCommit' => 'Is there such thing as destruct in memory caching?',
    ];

    /** @return InMemoryCache */
    public function createCachePool(): \Psr\Cache\CacheItemPoolInterface
    {
        return new InMemoryCache();
    }
}
