<?php

declare(strict_types=1);

namespace Qubus\Tests\Cache;

use Qubus\Cache\InMemoryCache;

class InMemoryCachePsr16IntegrationTest extends SimpleCacheTest
{
    public function createSimpleCache()
    {
        return new InMemoryCache();
    }
}
