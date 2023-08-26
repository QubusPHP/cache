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

namespace Qubus\Cache;

use DateInterval;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;
use Qubus\Cache\Adapter\CacheAdapter;
use Qubus\Cache\Psr16\SimpleCache;
use Qubus\Cache\Psr6\ItemPool;

abstract class BaseCache implements CacheInterface, CacheItemPoolInterface
{
    protected CacheItemPoolInterface $pool;

    protected CacheInterface $cache;

    /** @var CacheInterface|CacheAdapter|null $adapter */
    protected CacheInterface|CacheAdapter|null $adapter;

    public function __construct(int|null|DateInterval $ttl = null, ?string $namespace = null)
    {
        $this->cache = new SimpleCache($this->adapter, $ttl, $namespace ?? 'default');
        $this->pool = new ItemPool($this->adapter, $ttl, $namespace ?? 'default');

        unset($this->adapter);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Psr\SimpleCache\CacheInterface::get()
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cache->get($key, $default);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Psr\SimpleCache\CacheInterface::set()
     */
    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        return $this->cache->set($key, $value, $ttl);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Psr\SimpleCache\CacheInterface::delete()
     */
    public function delete(string $key): bool
    {
        return $this->cache->delete($key);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Psr\SimpleCache\CacheInterface::clear()
     * @see \Psr\Cache\CacheInterface::clear()
     * @see \Qubus\Cache\Psr16\Psr16Cache::clear()
     */
    public function clear(): bool
    {
        return $this->cache->clear() && $this->pool->clear();
    }

    /**
     * {@inheritdoc}
     *
     * @see \Psr\SimpleCache\CacheInterface::getMultiple()
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        return $this->cache->getMultiple($keys, $default);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Psr\SimpleCache\CacheInterface::setMultiple()
     */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        return $this->cache->setMultiple($values, $ttl);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Psr\SimpleCache\CacheInterface::deleteMultiple()
     */
    public function deleteMultiple(iterable $keys): bool
    {
        return $this->cache->deleteMultiple($keys);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Psr\SimpleCache\CacheInterface::has()
     */
    public function has(string $key): bool
    {
        return $this->cache->has($key);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Psr\Cache\CacheItemPoolInterface::getItem()
     */
    public function getItem(string $key): CacheItemInterface
    {
        return $this->pool->getItem($key);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Psr\Cache\CacheItemPoolInterface::getItems()
     */
    public function getItems(array $keys = []): iterable
    {
        return $this->pool->getItems($keys);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Psr\Cache\CacheItemPoolInterface::hasItem()
     */
    public function hasItem(string $key): bool
    {
        return $this->pool->hasItem($key);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Psr\Cache\CacheItemPoolInterface::deleteItem()
     */
    public function deleteItem(string $key): bool
    {
        return $this->pool->deleteItem($key);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Psr\Cache\CacheItemPoolInterface::deleteItems()
     */
    public function deleteItems(array $keys): bool
    {
        return $this->pool->deleteItems($keys);
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     * @see \Psr\Cache\CacheItemPoolInterface::save()
     */
    public function save(CacheItemInterface $item): bool
    {
        return $this->pool->save($item);
    }

    /**
     * {@inheritdoc}
     *
     * @throws TypeException
     * @see \Psr\Cache\CacheItemPoolInterface::saveDeferred()
     */
    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->pool->saveDeferred($item);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Psr\Cache\CacheItemPoolInterface::commit()
     */
    public function commit(): bool
    {
        return $this->pool->commit();
    }
}
