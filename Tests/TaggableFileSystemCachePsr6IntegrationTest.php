<?php

declare(strict_types=1);

namespace Qubus\Tests\Cache;

use Qubus\Cache\Adapter\FileSystemCacheAdapter;
use Qubus\Cache\Psr6\ItemPool;
use Qubus\Cache\Psr6\TaggablePsr6PoolAdapter;
use Qubus\Config\Collection;
use Qubus\FileSystem\Adapter\LocalFlysystemAdapter;
use Qubus\FileSystem\FileSystem;

class TaggableFileSystemCachePsr6IntegrationTest extends TaggableCachePoolTest
{
    /** @return TaggablePsr6PoolAdapter */
    public function createCachePool()
    {
        $config = Collection::factory([
            'path' => __DIR__ . '/config',
        ]);

        $localAdapter = new LocalFlysystemAdapter($config);
        $filesystem = new FileSystem($localAdapter);

        return TaggablePsr6PoolAdapter::makeTaggable(new ItemPool(new FileSystemCacheAdapter($filesystem)));
    }
}
