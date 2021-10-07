<?php

/**
 * Qubus\Cache
 *
 * @link       https://github.com/QubusPHP/cache
 * @copyright  2021 Joshua Parker <josh@joshuaparker.blog>
 * @license    https://opensource.org/licenses/mit-license.php MIT License
 *
 * @since      1.0.0
 */

declare(strict_types=1);

namespace Qubus\Cache\Adapter;

use APCuIterator;

class ApcuCacheAdapter extends Multiple implements CacheAdapter
{
    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::get()
     */
    public function get(string $key)
    {
        return false !== $value = apcu_fetch($key) ? $value : null;
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::set()
     */
    public function set(string $key, mixed $value, ?int $ttl): bool
    {
        return apcu_store($key, $value, $ttl ?? 0);
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::delete()
     */
    public function delete(string $key): bool
    {
        return apcu_delete($key);
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
    public function purge(?string $pattern): void
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
