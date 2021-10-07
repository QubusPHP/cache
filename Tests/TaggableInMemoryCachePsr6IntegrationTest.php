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

namespace Qubus\Tests\Cache;

use Qubus\Cache\Adapter\InMemoryCacheAdapter;
use Qubus\Cache\Psr6\ItemPool;
use Qubus\Cache\Psr6\TaggablePsr6PoolAdapter;

class TaggableInMemoryCachePsr6IntegrationTest extends TaggableCachePoolTest
{
    /** @return TaggablePsr6PoolAdapter */
    public function createCachePool()
    {
        return TaggablePsr6PoolAdapter::makeTaggable(new ItemPool(new InMemoryCacheAdapter()));
    }
}
