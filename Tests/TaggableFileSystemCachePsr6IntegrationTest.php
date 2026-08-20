<?php

declare(strict_types=1);

namespace Qubus\Tests\Cache;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Qubus\Cache\Adapter\FileSystemCacheAdapter;
use Qubus\Cache\Psr6\ItemPool;
use Qubus\Cache\Psr6\TaggablePsr6PoolAdapter;

use function getmypid;
use function sys_get_temp_dir;

class TaggableFileSystemCachePsr6IntegrationTest extends TaggableCachePoolTest
{
    /** @return TaggablePsr6PoolAdapter */
    public function createCachePool(): \Qubus\Cache\Psr6\TaggableCacheItemPool
    {
        $localAdapter = new LocalFilesystemAdapter(
            sys_get_temp_dir() . '/qubus-cache-tests-taggable-' . getmypid()
        );
        $filesystem = new Filesystem($localAdapter);

        return TaggablePsr6PoolAdapter::makeTaggable(new ItemPool(new FileSystemCacheAdapter($filesystem)));
    }
}
