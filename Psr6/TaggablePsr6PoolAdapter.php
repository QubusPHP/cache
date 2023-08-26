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

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

use Psr\Cache\InvalidArgumentException;

use function array_filter;
use function array_merge;
use function is_array;

/**
 * This adapter lets you make any PSR-6 cache pool taggable. If a pool is
 * already taggable, it is simply returned by makeTaggable. Tags are stored
 * either in the same cache pool, or a separate pool, and both of these
 * approaches come with different caveats.
 *
 * A general caveat is that using this adapter reserves any cache key starting
 * with '__tag.'.
 *
 * Using the same pool is precarious if your cache does LRU evictions of items
 * even if they do not expire (as in e.g. memcached). If so, the tag item may
 * be evicted without all the tagged items having been evicted first,
 * causing items to lose their tags.
 *
 * In order to mitigate this issue, you may use a separate, more persistent
 * pool for your tag items. Do however note that if you are doing so, the
 * entire pool is reserved for tags, as this pool is cleared whenever the
 * main pool is cleared.
 *
 * @author Magnus Nordlander <magnus@fervo.se>
 */
final class TaggablePsr6PoolAdapter implements TaggableCacheItemPool
{
    /** @var CacheItemPoolInterface $cachePool */
    private CacheItemPoolInterface $cachePool;

    /** @var ?CacheItemPoolInterface $tagStorePool */
    private ?CacheItemPoolInterface $tagStorePool;

    private function __construct(CacheItemPoolInterface $cachePool, ?CacheItemPoolInterface $tagStorePool = null)
    {
        $this->cachePool = $cachePool;
        if ($tagStorePool) {
            $this->tagStorePool = $tagStorePool;
        } else {
            $this->tagStorePool = $cachePool;
        }
    }

    /**
     * @param CacheItemPoolInterface      $cachePool    The pool to which to add tagging capabilities
     * @param CacheItemPoolInterface|null $tagStorePool The pool to store tags in. If null is passed,
     *                                                  the main pool is used.
     */
    public static function makeTaggable(
        CacheItemPoolInterface $cachePool,
        ?CacheItemPoolInterface $tagStorePool = null
    ): TaggableCacheItemPool {
        if ($cachePool instanceof TaggableCacheItemPool && $tagStorePool === null) {
            return $cachePool;
        }

        return new self($cachePool, $tagStorePool);
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key): TaggableCacheItem
    {
        return TaggablePsr6ItemAdapter::makeTaggable($this->cachePool->getItem($key));
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = []): iterable
    {
        $items = $this->cachePool->getItems($keys);

        $wrappedItems = [];
        foreach ($items as $key => $item) {
            $wrappedItems[$key] = TaggablePsr6ItemAdapter::makeTaggable($item);
        }

        return $wrappedItems;
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem(string $key): bool
    {
        return $this->cachePool->hasItem($key);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $ret = $this->cachePool->clear();

        return $this->tagStorePool->clear() && $ret; // Is this acceptable?
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem(string $key): bool
    {
        $this->preRemoveItem($key);

        return $this->cachePool->deleteItem($key);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->preRemoveItem($key);
        }

        return $this->cachePool->deleteItems($keys);
    }

    /**
     * {@inheritdoc}
     * @throws InvalidArgumentException
     */
    public function save(TaggableCacheItem|CacheItemInterface $item): bool
    {
        $this->removeTagEntries($item);
        $this->saveTags($item);

        return $this->cachePool->save($item->unwrap());
    }

    /**
     * {@inheritdoc}
     * @throws InvalidArgumentException
     */
    public function saveDeferred(TaggableCacheItem|CacheItemInterface $item): bool
    {
        $this->saveTags($item);

        return $this->cachePool->saveDeferred($item->unwrap());
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): bool
    {
        return $this->tagStorePool->commit() && $this->cachePool->commit();
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function appendListItem($name, $value): void
    {
        $listItem = $this->tagStorePool->getItem($name);
        if (! is_array($list = $listItem->get())) {
            $list = [];
        }

        $list[] = $value;
        $listItem->set($list);
        $this->tagStorePool->save($listItem);
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function removeList($name): bool
    {
        return $this->tagStorePool->deleteItem($name);
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function removeListItem($name, $key): void
    {
        $listItem = $this->tagStorePool->getItem($name);
        if (! is_array($list = $listItem->get())) {
            $list = [];
        }

        $list = array_filter($list, function ($value) use ($key) {
            return $value !== $key;
        });

        $listItem->set($list);
        $this->tagStorePool->save($listItem);
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function getList($name)
    {
        $listItem = $this->tagStorePool->getItem($name);
        if (! is_array($list = $listItem->get())) {
            $list = [];
        }

        return $list;
    }

    protected function getTagKey(string $tag): string
    {
        return '__tag.' . $tag;
    }

    /**
     * @return $this
     * @throws InvalidArgumentException
     */
    private function saveTags(TaggablePsr6ItemAdapter $item): static
    {
        $tags = $item->getTags();
        foreach ($tags as $tag) {
            $this->appendListItem($this->getTagKey($tag), $item->getKey());
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateTags(array $tags): bool
    {
        $itemIds = [];
        foreach ($tags as $tag) {
            $itemIds = array_merge($itemIds, $this->getList($this->getTagKey($tag)));
        }

        // Remove all items with the tag
        $success = $this->deleteItems($itemIds);

        if ($success) {
            // Remove the tag list
            foreach ($tags as $tag) {
                $this->removeList($this->getTagKey($tag));
            }
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateTag($tag): bool
    {
        return $this->invalidateTags([$tag]);
    }

    /**
     * Removes the key form all tag lists.
     *
     * @return $this
     * @throws InvalidArgumentException
     */
    private function preRemoveItem(string $key): static
    {
        $item = $this->getItem($key);
        $this->removeTagEntries($item);

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function removeTagEntries(TaggableCacheItem $item): void
    {
        $tags = $item->getPreviousTags();
        foreach ($tags as $tag) {
            $this->removeListItem($this->getTagKey($tag), $item->getKey());
        }
    }
}
