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

use function array_keys;
use function preg_match;
use function Qubus\Support\Helpers\is_null__;
use function serialize;
use function time;
use function unserialize;

class InMemoryCacheAdapter extends Multiple implements CacheAdapter
{
    private array $cache = [];

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::get()
     */
    public function get(string $key): mixed
    {
        // expired data should be deleted first.
        if (! $this->has($key)) {
            return null;
        }

        $cache = unserialize($this->cache[$key]);

        return null === $cache['value'] ? null : $cache['value'];
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::set()
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        if (null !== $ttl && $ttl <= 0) {
            return $this->delete($key);
        }

        $cache = [
            'key'   => $key,
            'ttl'   => null === $ttl ? null : time() + $ttl,
            'value' => $value,
        ];

        $this->cache[$key] = serialize($cache);

        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::delete()
     */
    public function delete(string $key): bool
    {
        unset($this->cache[$key]);

        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::has()
     */
    public function has(string $key): bool
    {
        if (! isset($this->cache[$key])) {
            return false;
        }

        $data = unserialize($this->cache[$key]);

        if (null !== $data['ttl'] && $data['ttl'] <= time()) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::purge()
     */
    public function purge(?string $pattern = null): void
    {
        if (empty($this->cache)) {
            return;
        }

        if (is_null__($pattern)) {
            $this->cache = [];

            return;
        }

        $keys = [];
        foreach (array_keys($this->cache) as $key) {
            if (1 === preg_match("#{$pattern}#", $key)) {
                $keys[] = $key;
            }
        }

        $this->deleteMultiple($keys);
    }
}
