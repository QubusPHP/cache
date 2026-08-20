<?php

/**
 * Qubus\Cache
 *
 * @link       https://github.com/QubusPHP/cache
 * @copyright  2021
 * @author     Joshua Parker <joshua@joshuaparker.dev>
 * @license    https://opensource.org/licenses/mit-license.php MIT License
 */

declare(strict_types=1);

namespace Qubus\Cache\Adapter;

use APCuIterator;

use function apcu_clear_cache;
use function apcu_delete;
use function apcu_exists;
use function apcu_fetch;
use function apcu_store;

class ApcuCacheAdapter extends Multiple implements CacheAdapter
{
    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::get()
     */
    public function get(string $key): mixed
    {
        $value = apcu_fetch($key);

        return false !== $value ? $value : null;
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::set()
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        if (null !== $ttl && $ttl <= 0) {
            apcu_delete($key);
            return true;
        }

        return apcu_store($key, $value, $ttl ?? 0);
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::delete()
     */
    public function delete(string $key): bool
    {
        return ! apcu_exists($key) || apcu_delete($key);
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::has()
     */
    public function has(string $key): bool
    {
        return apcu_exists($key);
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::purge()
     */
    public function purge(?string $pattern = null): void
    {
        if (null === $pattern) {
            apcu_clear_cache();

            return;
        }

        foreach (new APCuIterator("#{$pattern}#", APC_ITER_KEY) as $key => $value) {
            apcu_delete($key);
        }
    }
}
