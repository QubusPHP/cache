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

use Qubus\Cache\InMemoryCache;

class InMemoryCachePsr16IntegrationTest extends SimpleCacheTest
{
    public function createSimpleCache()
    {
        return new InMemoryCache();
    }
}
