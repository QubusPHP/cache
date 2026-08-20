<?php

/**
 * Qubus\Cache
 *
 * @link       https://github.com/QubusPHP/cache
 * @copyright  2025
 * @author     Joshua Parker <joshua@joshuaparker.dev>
 * @license    https://opensource.org/licenses/mit-license.php MIT License
 */

declare(strict_types=1);

namespace Qubus\Cache\Adapter;

use Closure;
use Predis\Collection\Iterator\Keyspace;
use Predis\ClientInterface;
use Qubus\Cache\TypeException;

use function array_combine;
use function array_keys;
use function serialize;
use function unserialize;

class PredisCacheAdapter extends Multiple implements CacheAdapter
{
    public function __construct(private readonly ClientInterface $client)
    {
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::get()
     */
    public function get(string $key): mixed
    {
        $value = $this->client->get($key);

        return null === $value ? null : @unserialize($value, ['allowed_classes' => true]);
    }

    /**
     * {@inheritDoc}
     *
     * @throws TypeException
     * @see \Qubus\Cache\Adapter\CacheAdapter::set()
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        if (is_int($ttl)) {
            $expires = $ttl;
        } elseif ($ttl === null) {
            $expires = (int) 0;
        } else {
            throw new TypeException("Invalid TTL value.");
        }

        if ($expires < 1 && null !== $ttl) {
            $this->client->del($key);
            return true;
        } elseif ($expires > 0) {
            return 'OK' === $this->client->setex(key: $key, seconds: $expires, value: serialize($value))->getPayload();
        } else {
            return 'OK' === $this->client->set(key: $key, value: serialize($value))->getPayload();
        }
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::delete()
     */
    public function delete(string $key): bool
    {
        $this->client->del($key);

        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::has()
     */
    public function has(string $key): bool
    {
        return 0 !== $this->client->exists($key);
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::getMultiple()
     */
    public function getMultiple(array $keys): ?array
    {
        $result = [];
        foreach ($keys as $key) {
            $value = $this->client->get($key);
            $result[] = null === $value ? null : @unserialize($value, ['allowed_classes' => true]);
        }

        return $result;
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
                    $this->set($key, $value['value'], $value['ttl'] ?? null);
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
        $this->pipeline(function () use ($keys): void {
            foreach ($keys as $key) {
                $this->client->del($key);
            }
        });

        return null;
    }

    /**
     * {@inheritDoc}
     *
     * @see \Qubus\Cache\Adapter\CacheAdapter::purge()
     */
    public function purge(?string $pattern = null): void
    {
        if (null === $pattern) {
            $this->client->flushAll();
            return;
        }

        foreach (new Keyspace($this->client, "*{$pattern}*", 1000) as $key) {
            $this->client->del($key);
        }
    }

    private function pipeline(Closure $action): ?array
    {
        $this->client->multi();
        $action->call($this);

        return $this->client->exec();
    }
}
