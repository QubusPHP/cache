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

use Closure;
use Redis;

use function array_combine;
use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function Qubus\Support\Helpers\is_false__;
use function Qubus\Support\Helpers\is_null__;

class RedisCacheAdapter extends Multiple implements CacheAdapter
{
    public function __construct(private Redis $redis)
    {
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::get()
     */
    public function get(string $key)
    {
        return false === $value = $this->redis->get($key) ? null : $value;
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::set()
     */
    public function set(string $key, mixed $value, ?int $ttl): bool
    {
        return is_null__($ttl) ? $this->redis->set($key, $value) : $this->redis->setEx($key, $ttl, $value);
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::delete()
     */
    public function delete(string $key): bool
    {
        return 0 !== $this->redis->delete($key);
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::has()
     */
    public function has(string $key): bool
    {
        return 0 !== $this->redis->exists($key);
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::getMultiple()
     */
    public function getMultiple(array $keys): ?array
    {
        return array_map(function ($result) {
            return is_false__($result) ? null : $result;
        }, $this->pipeline(function () use ($keys): void {
            foreach ($keys as $key) {
                $this->redis->get($key);
            }
        }));
    }

    /**
     * {@inheritdoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::setMultiple()
     */
    public function setMultiple(array $values): ?array
    {
        $results = null;

        foreach (
            array_combine(array_keys($values), $this->pipeline(function () use ($values): void {
                foreach ($values as $key => $value) {
                    is_null__($value['ttl']) ?
                    $this->redis->set($key, $value['value']) :
                    $this->redis->setEx($key, $value['ttl'], $value['value']);
                }
            })) as $key => $result
        ) {
            if (! $result) {
                $results[] = $key;
            }
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::deleteMultiple()
     */
    public function deleteMultiple(array $keys): ?array
    {
        $misses = array_filter(array_map(function (bool $result, string $key): ?string {
            if (! $result) {
                return $key;
            }
            return null;
        }, $this->pipeline(function () use ($keys): void {
            foreach ($keys as $key) {
                $this->redis->delete($key);
            }
        }), $keys));

        return empty($misses) ? null : array_values($misses);
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::purge()
     */
    public function purge(?string $pattern): void
    {
        if (is_null__($pattern)) {
            $this->redis->flushAll();

            return;
        }

        if (null !== $prefix = $this->redis->getOption(Redis::OPT_PREFIX)) {
            $this->redis->setOption(Redis::OPT_PREFIX, "");
        }

        while ($keys = $this->redis->scan($iterator, "*{$pattern}*", 1000)) {
            $this->deleteMultiple($keys);
        }

        if (! is_null__($prefix)) {
            $this->redis->setOption(Redis::OPT_PREFIX, $prefix);
        }
    }

    private function pipeline(Closure $action): array
    {
        $this->redis->multi(Redis::PIPELINE);
        $action->call($this);

        return $this->redis->exec();
    }
}
