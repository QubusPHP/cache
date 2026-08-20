<?php

declare(strict_types=1);

namespace Qubus\Tests\Cache;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Qubus\Cache\FileSystemCache;

use function getmypid;
use function sys_get_temp_dir;

class FileSystemCachePsr6IntegrationTest extends CachePoolTest
{
    /** @return FileSystemCache */
    public function createCachePool(): \Psr\Cache\CacheItemPoolInterface
    {
        $localAdapter = new LocalFilesystemAdapter(sys_get_temp_dir() . '/qubus-cache-tests-psr6-' . getmypid());
        $filesystem = new Filesystem($localAdapter);

        return new FileSystemCache($filesystem);
    }
}
