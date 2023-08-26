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

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

/**
 * Interface for invalidating cached items using tags. This interface is a soon-to-be-PSR.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface TaggableCacheItemPool extends CacheItemPoolInterface
{
    /**
     * Invalidates cached items using a tag.
     *
     * @param string $tag The tag to invalidate
     * @throws InvalidArgumentException When $tags is not valid.
     * @return bool True on success
     */
    public function invalidateTag(string $tag): bool;

    /**
     * Invalidates cached items using tags.
     *
     * @param string[] $tags An array of tags to invalidate
     * @throws InvalidArgumentException When $tags is not valid.
     * @return bool True on success
     */
    public function invalidateTags(array $tags): bool;
}
