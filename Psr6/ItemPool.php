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
use Qubus\Support\DateTime\QubusDateTimeImmutable;

use function array_key_exists;
use function count;
use function in_array;
use function is_int;
use function is_object;

final class ItemPool implements CacheItemPoolInterface
{
    use ValidatableKeyAware;

    /** @var CacheItemInterface[] $deferred */
    protected array $deferredItems = [];

    public const string CACHE_FLAG = "@psr6_";

    private readonly ?string $namespace;

    /**
     */
    public function __construct(
        private readonly CacheAdapter $adapter,
        private readonly int|null|DateInterval $ttl = null,
        ?string $namespace = 'default',
        private readonly ?int $autoCommitCount = null
    ) {
        $this->namespace = $this->normalizeNamespace($namespace);
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
        $validatedKey = $this->validateKey($key);

        if (array_key_exists($validatedKey, $this->deferredItems)) {
            $value = $this->deferredItems[$validatedKey];
            $item = is_object($value) ? clone $value : $value;

            $item->setHit(! $item->isExpired());

            return $item;
        }

        $value = $this->adapter->get($validatedKey);
        $isHit = null !== $value || $this->adapter->has($validatedKey);

        return new Item($key, $value, $this->defaultExpiration(), $isHit);
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = []): iterable
    {
        if (empty($keys)) {
            return [];
        }

        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key);
        }

        return $items;
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem(string $key): bool
    {
        $validatedKey = $this->validateKey($key);
        if (isset($this->deferredItems[$validatedKey])) {
            if ($this->deferredItems[$validatedKey]->isExpired()) {
                return false;
            }
            return true;
        }

        return $this->adapter->has($validatedKey);
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
        $validatedKey = $this->validateKey($key);
        unset($this->deferredItems[$validatedKey]);

        return ! $this->adapter->has($validatedKey) || $this->adapter->delete($validatedKey);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys): bool
    {
        $validatedKeys = [];
        foreach ($keys as $key) {
            $validatedKey = $this->validateKey($key);
            unset($this->deferredItems[$validatedKey]);
            $validatedKeys[] = $validatedKey;
        }

        return null === $this->adapter->deleteMultiple($validatedKeys);
    }

    /**
     * {@inheritdoc}
     * @throws InvalidArgumentException
     */
    public function save(CacheItemInterface $item): bool
    {
        if (! $item instanceof Item) {
            throw new TypeException('Cache items must be created by this pool.');
        }

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
        if (! $item instanceof Item) {
            throw new TypeException('Cache items must be created by this pool.');
        }

        $this->deferredItems[$this->validateKey($item->getKey())] = $item;

        if (null !== $this->autoCommitCount && count($this->deferredItems) >= $this->autoCommitCount) {
            return $this->commit();
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): bool
    {
        if ([] === $this->deferredItems) {
            return true;
        }

        $entries = array_map(function ($item) {
            return [
                'key' => $item->getKey(),
                'value' => $item->get(),
                'ttl' => $this->getTtl($item->getExpiresInSeconds()),
            ];
        }, $this->deferredItems);

        $failedKeys = $this->adapter->setMultiple($entries);
        if (null === $failedKeys) {
            $this->deferredItems = [];
            return true;
        }

        foreach ($this->deferredItems as $key => $item) {
            if (! in_array($key, $failedKeys, true)) {
                unset($this->deferredItems[$key]);
            }
        }

        return false;
    }

    protected function getTtl(int|null|DateInterval $ttl = null): ?int
    {
        if (is_int($ttl)) {
            return $ttl;
        }

        if ($ttl instanceof DateInterval) {
            return DateIntervalConverter::convert($ttl);
        }

        return null;
    }

    private function defaultExpiration(): QubusDateTimeImmutable
    {
        $ttl = $this->getTtl($this->ttl);

        return null === $ttl
        ? new QubusDateTimeImmutable(Item::EXPIRATION)
        : new QubusDateTimeImmutable("now +$ttl seconds");
    }
}
