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

namespace Qubus\Cache\Psr6;

use DateInterval;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Qubus\Cache\Adapter\CacheAdapter;
use Qubus\Cache\DateIntervalConverter;
use Qubus\Cache\Traits\ValidatableKeyAware;
use Qubus\Cache\TypeException;
use Qubus\Exception\Exception;

use function array_combine;
use function array_key_exists;
use function array_keys;
use function array_map;
use function count;
use function is_int;
use function is_object;
use function Qubus\Support\Helpers\is_null__;

final class ItemPool implements CacheItemPoolInterface
{
    use ValidatableKeyAware;

    /** @var CacheItemInterface[] $deferred */
    protected array $deferredItems = [];

    public const CACHE_FLAG = "@psr6_";

    /**
     */
    public function __construct(
        private readonly CacheAdapter $adapter,
        private readonly int|null|DateInterval $ttl = null,
        private readonly ?string $namespace = 'default',
        private readonly ?int $autoCommitCount = null
    ) {
    }

    /**
     * Commit any pending deferred items.
     */
    public function __destruct()
    {
        if ($this->deferredItems) {
            $this->commit();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getItem(string $key): CacheItemInterface
    {
        if (array_key_exists($this->validateKey($key), $this->deferredItems)) {
            $value = $this->deferredItems[$this->validateKey($key)];
            $item = is_object($value) ? clone $value : $value;

            $item->setHit(! $item->isExpired());

            return $item;
        }

        $value = $this->adapter->get($this->validateKey($key));

        return new Item($key, $value, null, null === $value ? false : true);
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = []): iterable
    {
        if (empty($keys)) {
            return [];
        }

        return array_combine(
            $keys,
            array_map(function (?string $item, string $key): CacheItemInterface {
                return null !== $item ? new Item($item) :
                    $this->deferredItems[$this->validateKey($key)] ?? new Item($key);
            }, (array) $this->adapter->getMultiple(array_map([$this, 'validateKey'], $keys)), $keys)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem(string $key): bool
    {
        if (isset($this->deferredItems[$this->validateKey($key)])) {
            if ($this->deferredItems[$this->validateKey($key)]->isExpired()) {
                return false;
            }
            return true;
        }

        return $this->adapter->has($this->validateKey($key));
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $this->adapter->purge(self::CACHE_FLAG . $this->namespace);

        $this->deferredItems = [];

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem(string $key): bool
    {
        $this->adapter->delete($this->validateKey($key));

        unset($this->deferredItems[$this->validateKey($key)]);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            unset($this->deferredItems[$this->validateKey($key)]);
        }

        return null === $this->adapter->deleteMultiple(array_map([$this, 'validateKey'], $keys));
    }

    /**
     * {@inheritdoc}
     * @throws InvalidArgumentException
     */
    public function save(CacheItemInterface $item): bool
    {
        if ($item->isExpired()) {
            return $this->deleteItem($item->getKey());
        }

        return $this->adapter->set(
            $this->validateKey($item->getKey()),
            $item->get(),
            $this->getTtl($item->getExpiresInSeconds())
        );
    }

    /**
     * {@inheritdoc}
     * @throws TypeException
     */
    public function saveDeferred(CacheItemInterface $item): bool
    {
        $this->deferredItems[$this->validateKey($item->getKey())] = $item;

        if (! is_null__($this->autoCommitCount) && count($this->deferredItems) >= $this->autoCommitCount) {
            return $this->commit();
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): bool
    {
        if (is_null__($this->deferredItems)) {
            return true;
        }

        $result = null === $this->adapter->setMultiple(
            array_combine(
                array_keys($this->deferredItems),
                array_map(function (CacheItemInterface $item): array {
                    return [
                        'key'   => $item->getKey(),
                        'value' => $item->get(),
                        "ttl"   => $this->getTtl($item->getExpiresInSeconds()),
                    ];
                }, $this->deferredItems)
            )
        );

        $this->deferredItems = [];

        return $result;
    }

    protected function getTtl(int|null|DateInterval $ttl): ?int
    {
        if (is_int($ttl)) {
            return $ttl === -1 ? $this->ttl : $ttl;
        }

        if ($ttl instanceof DateInterval) {
            return DateIntervalConverter::convert($ttl);
        }

        return null;
    }
}
