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

namespace Qubus\Cache\Psr16;

use DateInterval;
use Psr\SimpleCache\CacheInterface;
use Qubus\Cache\Adapter\CacheAdapter;
use Qubus\Cache\DateIntervalConverter;
use Qubus\Cache\Traits\ValidatableKeyAware;
use Traversable;

use function is_int;
use function iterator_to_array;

final class SimpleCache implements CacheInterface
{
    use ValidatableKeyAware;

    public const string CACHE_FLAG = "@psr16_";

    private readonly ?string $namespace;

    public function __construct(
        private readonly CacheAdapter $adapter,
        private readonly int|null|DateInterval $ttl = null,
        ?string $namespace = 'default'
    ) {
        $this->namespace = $this->normalizeNamespace($namespace);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $validatedKey = $this->validateKey($key);
        $value = $this->adapter->get($validatedKey);

        return null !== $value || $this->adapter->has($validatedKey) ? $value : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        return $this->adapter->set($this->validateKey($key), $value, $this->getTtl($ttl));
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        $validatedKey = $this->validateKey($key);

        return ! $this->adapter->has($validatedKey) || $this->adapter->delete($validatedKey);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $this->adapter->purge(self::CACHE_FLAG . $this->namespace);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        if ($keys instanceof Traversable) {
            $keys = iterator_to_array($keys, false);
        }

        $values = [];
        foreach ($keys as $key) {
            $key = (string) $key;
            $values[$key] = $this->get($key, $default);
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        if ($values instanceof Traversable) {
            $values = iterator_to_array($values);
        }

        $entries = [];
        foreach ($values as $key => $value) {
            $entries[$this->validateKey((string) $key)] = [
                'value' => $value,
                'ttl'   => $this->getTtl($ttl),
            ];
        }

        return null === $this->adapter->setMultiple($entries);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(iterable $keys): bool
    {
        if ($keys instanceof Traversable) {
            $keys = iterator_to_array($keys, false);
        }

        $validatedKeys = [];
        foreach ($keys as $key) {
            $validatedKeys[] = $this->validateKey((string) $key);
        }

        return null === $this->adapter->deleteMultiple($validatedKeys);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return $this->adapter->has($this->validateKey($key));
    }

    private function getTtl(int|null|DateInterval $ttl = null): ?int
    {
        $ttl ??= $this->ttl;

        if (is_int($ttl)) {
            return $ttl;
        }

        if ($ttl instanceof DateInterval) {
            return DateIntervalConverter::convert($ttl);
        }

        return null;
    }
}
