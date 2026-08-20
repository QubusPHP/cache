<?php

declare(strict_types=1);

namespace Qubus\Tests\Cache;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Qubus\Cache\FileSystemCache;

use function getmypid;
use function sys_get_temp_dir;

class FileSystemCachePsr16IntegrationTest extends SimpleCacheTest
{
    private Filesystem $filesystem;

    public function createSimpleCache(): \Psr\SimpleCache\CacheInterface
    {
        $localAdapter = new LocalFilesystemAdapter(sys_get_temp_dir() . '/qubus-cache-tests-psr16-' . getmypid());
        $this->filesystem = new Filesystem($localAdapter);

        return new FileSystemCache($this->filesystem);
    }

    public function testExpiredFileIsDeletedWhenRead(): void
    {
        $path = $this->cachePath('expired-file');
        $this->assertTrue($this->cache->set('expired-file', 'value', 1));
        $this->assertTrue($this->filesystem->fileExists($path));

        $this->advanceTime(2);

        $this->assertNull($this->cache->get('expired-file'));
        $this->assertFalse($this->filesystem->fileExists($path));
    }

    public function testNonPositiveTtlDeletesExistingFileImmediately(): void
    {
        $path = $this->cachePath('immediately-expired-file');
        $this->assertTrue($this->cache->set('immediately-expired-file', 'old value'));
        $this->assertTrue($this->filesystem->fileExists($path));

        $this->assertTrue($this->cache->set('immediately-expired-file', 'new value', 0));

        $this->assertFalse($this->filesystem->fileExists($path));
        $this->assertFalse($this->cache->has('immediately-expired-file'));
    }

    public function testMalformedCacheFileIsDeleted(): void
    {
        $path = $this->cachePath('malformed-file');
        $this->filesystem->write($path, 'not a serialized cache entry');

        $this->assertSame('fallback', $this->cache->get('malformed-file', 'fallback'));
        $this->assertFalse($this->filesystem->fileExists($path));
    }

    public function testClearContinuesPastDirectories(): void
    {
        $path = $this->cachePath('clear-with-directory');
        $this->filesystem->write('nested/unrelated-file', 'keep');
        $this->cache->set('clear-with-directory', 'value');

        $this->assertTrue($this->cache->clear());

        $this->assertFalse($this->filesystem->fileExists($path));
        $this->assertTrue($this->filesystem->fileExists('nested/unrelated-file'));
        $this->filesystem->delete('nested/unrelated-file');
        $this->filesystem->deleteDirectory('nested');
    }

    public function testPruneDeletesUnaccessedExpiredFilesAndPreservesOtherFiles(): void
    {
        $path = $this->cachePath('unaccessed-expired-file');
        $this->cache->set('unaccessed-expired-file', 'value', 1);
        $this->filesystem->write('unrelated-file', 'keep');

        $this->advanceTime(2);

        $this->assertInstanceOf(FileSystemCache::class, $this->cache);
        $this->assertTrue($this->cache->prune());
        $this->assertFalse($this->filesystem->fileExists($path));
        $this->assertTrue($this->filesystem->fileExists('unrelated-file'));
        $this->filesystem->delete('unrelated-file');
    }

    private function cachePath(string $key): string
    {
        return '@psr16_default_' . sha1($key);
    }
}
