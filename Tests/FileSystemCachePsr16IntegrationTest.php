<?php

declare(strict_types=1);

namespace Qubus\Tests\Cache;

use Qubus\Cache\FileSystemCache;
use Qubus\Config\Collection;
use Qubus\FileSystem\Adapter\LocalFlysystemAdapter;
use Qubus\FileSystem\FileSystem;

class FileSystemCachePsr16IntegrationTest extends SimpleCacheTest
{
    public function createSimpleCache()
    {
        $config = Collection::factory([
            'path' => __DIR__ . '/config',
        ]);

        $localAdapter = new LocalFlysystemAdapter($config);
        $filesystem = new FileSystem($localAdapter);
        return new FileSystemCache($filesystem);
    }
}
