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
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;
use Qubus\Cache\TypeException;

use function count;
use function gettype;
use function is_array;
use function is_object;
use function is_string;
use function Qubus\Support\Helpers\array_key_exists__;
use function sprintf;
use function strcspn;

/**
 * @internal
 *
 * An adapter for non-taggable cache items, to be used with the cache pool
 * adapter.
 *
 * This adapter stores tags along with the cached value, by storing wrapping
 * the item in an array structure containing both.
 *
 * @author Magnus Nordlander <magnus@fervo.se>
 */
final class TaggablePsr6ItemAdapter implements TaggableCacheItem
{
    /** @var bool $initialized */
    private bool $initialized = false;

    /** @var array $prevTags */
    private array $prevTags = [];

    /** @var array $tags */
    private array $tags = [];

    private function __construct(private CacheItemInterface $cacheItem)
    {
    }

    public static function makeTaggable(CacheItemInterface $cacheItem): TaggablePsr6ItemAdapter
    {
        return new self($cacheItem);
    }

    public function unwrap(): CacheItemInterface
    {
        return $this->cacheItem;
    }

    /**
     * {@inheritdoc}
     */
    public function getKey(): string
    {
        return $this->cacheItem->getKey();
    }

    /**
     * {@inheritdoc}
     */
    public function get(): mixed
    {
        $rawItem = $this->cacheItem->get();

        // If it is a cache item we created
        if ($this->isItemCreatedHere($rawItem)) {
            return $rawItem['value'];
        }

        // This is an item stored before we used this fake cache
        return $rawItem;
    }

    /**
     * {@inheritdoc}
     */
    public function isHit(): bool
    {
        return $this->cacheItem->isHit();
    }

    /**
     * {@inheritdoc}
     */
    public function set($value): static
    {
        $this->initializeTags();

        $this->cacheItem->set([
            'value' => $value,
            'tags'  => $this->tags,
        ]);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getPreviousTags(): array
    {
        $this->initializeTags();

        return $this->prevTags;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * {@inheritdoc}
     */
    public function setTags(array $tags): TaggableCacheItem
    {
        $this->tags = [];

        return $this->tag($tags);
    }

    /**
     * @param array|string $tags
     * @return TaggableCacheItem
     * @throws TypeException
     */
    private function tag(array|string $tags): TaggableCacheItem
    {
        if (! is_array($tags)) {
            $tags = [$tags];
        }

        $this->initializeTags();

        foreach ($tags as $tag) {
            if (! is_string($tag)) {
                throw new TypeException(
                    sprintf(
                        'Cache tag must be string, "%s" given',
                        is_object($tag) ? $tag::class : gettype($tag)
                    )
                );
            }
            if (isset($this->tags[$tag])) {
                continue;
            }
            if (! isset($tag[0])) {
                throw new TypeException('Cache tag length must be greater than zero');
            }
            if (isset($tag[strcspn($tag, '{}()/\@:')])) {
                throw new TypeException(sprintf('Cache tag "%s" contains reserved characters {}()/\@:', $tag));
            }
            $this->tags[$tag] = $tag;
        }

        $this->updateTags();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function expiresAt(?DateTimeInterface $expiration): static
    {
        $this->cacheItem->expiresAt($expiration);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function expiresAfter(int|DateInterval|null $time): static
    {
        $this->cacheItem->expiresAfter($time);

        return $this;
    }

    private function updateTags(): void
    {
        $this->cacheItem->set([
            'value' => $this->get(),
            'tags'  => $this->tags,
        ]);
    }

    private function initializeTags(): void
    {
        if (! $this->initialized) {
            if ($this->cacheItem->isHit()) {
                $rawItem = $this->cacheItem->get();

                if ($this->isItemCreatedHere($rawItem)) {
                    $this->prevTags = $rawItem['tags'];
                }
            }

            $this->initialized = true;
        }
    }

    /**
     * Verify that the raw data is a cache item created by this class.
     *
     * @param mixed $rawItem
     * @return bool
     */
    private function isItemCreatedHere(mixed $rawItem): bool
    {
        return is_array($rawItem)
        && array_key_exists__('value', $rawItem)
        && array_key_exists__('tags', $rawItem)
        && count($rawItem) === 2;
    }
}
