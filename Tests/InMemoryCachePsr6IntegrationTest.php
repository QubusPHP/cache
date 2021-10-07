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

class InMemoryCachePsr6IntegrationTest extends CachePoolTest
{
    /** @var array $skippedTests  */
    protected $skippedTests = [
        'testSaveWithoutExpire'         => 'Need to investigate this test for in memory cache.',
        'testDeferredSaveWithoutCommit' => 'Is there such thing as destruct in memory caching?',
    ];

    /** @return InMemoryCache */
    public function createCachePool()
    {
        return new InMemoryCache();
    }
}
