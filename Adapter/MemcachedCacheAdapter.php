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

use Memcached;

use function preg_match;
use function Qubus\Support\Helpers\is_null__;

class MemcachedCacheAdapter extends Multiple implements CacheAdapter
{
    public function __construct(private Memcached $memcached)
    {
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::get()
     */
    public function get(string $key)
    {
        return false !== $value = $this->memcached->get($key) ? $value : null;
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::set()
     */
    public function set(string $key, mixed $value, ?int $ttl): bool
    {
        return $this->memcached->set($key, $value, $ttl ?? 0);
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::delete()
     */
    public function delete(string $key): bool
    {
        return $this->memcached->delete($key);
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::has()
     */
    public function has(string $key): bool
    {
        $result = $this->memcached->get($key);
        if (false === $result && Memcached::RES_NOTFOUND === $this->memcached->getResultCode()) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::purge()
     */
    public function purge(?string $pattern): void
    {
        if (is_null__($pattern)) {
            $this->memcached->flush();
        }

        if ('' !== $prefix = $this->memcached->getOption(Memcached::OPT_PREFIX_KEY)) {
            $this->memcached->setOption(Memcached::OPT_PREFIX_KEY, '');
        }

        foreach ($this->memcached->getAllKeys() as $key) {
            if (1 === preg_match("#{$pattern}#", $key)) {
                $this->memcached->delete($key);
            }
        }

        if ('' !== $prefix) {
            $this->memcached->setOption(Memcached::OPT_PREFIX_KEY, $prefix);
        }
    }
}
